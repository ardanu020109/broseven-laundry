<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Models\LaundryTransaction;
use App\Models\LaundryTransactionVerification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VerificationService
{
    public function __construct(
        private readonly QRCodeService $qr,
        private readonly LaundryStatusService $statuses,
        private readonly ActivityLogService $activity,
    ) {
    }

    /**
     * Mengenali input: token QR, URL verifikasi hasil scan, atau kode transaksi.
     * Transaksi yang sudah dihapus (soft delete) dianggap tidak ada.
     *
     * @return array{0: LaundryTransaction, 1: string}|null  [transaksi, sumber: qr|code]
     */
    public function resolve(string $input): ?array
    {
        $input = trim($input);

        $token = null;
        $code = null;

        if (preg_match('~/verify/([A-Za-z0-9]{48})(?:[/?#]|$)~', $input, $matches)) {
            $token = $matches[1];
        } elseif (preg_match('/^[A-Za-z0-9]{48}$/', $input)) {
            $token = $input;
        } elseif (preg_match('/^BS-\d{6}-\d{4}$/i', $input)) {
            $code = strtoupper($input);
        }

        if ($token !== null) {
            $transaction = LaundryTransaction::query()
                ->whereHas('verification', fn ($query) => $query->where('token', $token))
                ->with('verification')
                ->first();

            // Kolom MySQL tidak membedakan huruf besar/kecil: pastikan cocok persis.
            if (! $transaction || ! hash_equals((string) $transaction->verification->token, $token)) {
                return null;
            }

            return [$transaction, 'qr'];
        }

        if ($code !== null) {
            $transaction = LaundryTransaction::query()->where('code', $code)->first();

            return $transaction ? [$transaction, 'code'] : null;
        }

        return null;
    }

    /**
     * Memeriksa transaksi (dan mencatat scan). Null jika tidak ditemukan.
     *
     * @return array<string, mixed>|null
     */
    public function lookup(string $input): ?array
    {
        $resolved = $this->resolve($input);

        if ($resolved === null) {
            return null;
        }

        [$transaction, $source] = $resolved;

        $verification = $this->qr->ensureVerification($transaction);
        $verification->increment('scan_count', 1, ['last_scanned_at' => now()]);

        return $this->result($transaction, $verification, $source);
    }

    /**
     * Memproses pengambilan cucian. Hanya berhasil sekali: transaksi dan baris
     * verifikasi dikunci, lalu kondisi diperiksa ulang di dalam kunci.
     *
     * @return array<string, mixed>|null
     */
    public function complete(string $input, User $actor): ?array
    {
        $resolved = $this->resolve($input);

        if ($resolved === null) {
            return null;
        }

        [$found, $source] = $resolved;

        $transaction = DB::transaction(function () use ($found, $source, $actor) {
            // Urutan kunci sama dengan LaundryStatusService: transaksi dulu, baru verifikasi.
            $transaction = LaundryTransaction::query()->lockForUpdate()->findOrFail($found->getKey());

            $verification = $this->qr->ensureVerification($transaction);
            $verification = LaundryTransactionVerification::query()->lockForUpdate()->findOrFail($verification->getKey());

            $state = $this->evaluate($transaction, $verification);

            if (! $state['can_complete']) {
                throw ValidationException::withMessages(['code' => $state['message']]);
            }

            $via = $source === 'qr' ? 'QR Code' : 'kode transaksi';

            $this->statuses->complete($transaction, $actor, "Cucian diambil customer (verifikasi {$via})");

            $this->activity->log(
                'transaction.picked_up',
                "Pengambilan cucian {$transaction->code} melalui {$via}",
                $transaction,
                ['source' => $source],
                $actor
            );

            return $transaction;
        });

        $transaction->refresh()->load('verification');

        return [
            'valid' => true,
            'can_complete' => false,
            'reason' => 'COMPLETED',
            'message' => 'Cucian berhasil diserahkan kepada customer.',
            'source' => $source,
            'transaction' => $transaction,
        ];
    }

    /**
     * @return array{valid: bool, can_complete: bool, reason: string, message: string}
     */
    private function evaluate(LaundryTransaction $transaction, LaundryTransactionVerification $verification): array
    {
        if ($transaction->status === TransactionStatus::Cancelled) {
            return $this->state(false, false, 'CANCELLED', 'Transaksi ini telah dibatalkan.');
        }

        if ($verification->isUsed() || $transaction->status === TransactionStatus::Completed) {
            $when = ($transaction->completed_at ?? $verification->used_at)
                ?->timezone(config('app.timezone'))
                ->translatedFormat('d M Y, H:i');

            return $this->state(
                false,
                false,
                'ALREADY_USED',
                'QR Code sudah digunakan.'.($when ? " Cucian telah diambil pada {$when} WITA." : '')
            );
        }

        if ($verification->isExpired()) {
            return $this->state(false, false, 'EXPIRED', 'QR Code sudah kedaluwarsa.');
        }

        if ($transaction->status !== TransactionStatus::Ready) {
            return $this->state(
                true,
                false,
                'NOT_READY',
                "Transaksi valid, tetapi cucian belum siap diambil. Status saat ini: {$transaction->status->label()}."
            );
        }

        return $this->state(true, true, 'READY', 'Transaksi valid dan cucian siap diserahkan kepada customer.');
    }

    /**
     * @return array<string, mixed>
     */
    private function result(LaundryTransaction $transaction, LaundryTransactionVerification $verification, string $source): array
    {
        return $this->evaluate($transaction, $verification) + [
            'source' => $source,
            'transaction' => $transaction,
        ];
    }

    /**
     * @return array{valid: bool, can_complete: bool, reason: string, message: string}
     */
    private function state(bool $valid, bool $canComplete, string $reason, string $message): array
    {
        return [
            'valid' => $valid,
            'can_complete' => $canComplete,
            'reason' => $reason,
            'message' => $message,
        ];
    }
}