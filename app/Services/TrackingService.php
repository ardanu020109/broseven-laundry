<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Models\LaundryTransaction;
use App\Models\LaundryWebsiteSetting;
use App\Support\PhoneNumber;
use App\Support\Privacy;

class TrackingService
{
    /** Token tracking: 40 karakter alfanumerik. Pencocokan dibuat case-sensitive. */
    public function findByToken(string $token): ?LaundryTransaction
    {
        if (! preg_match('/^[A-Za-z0-9]{40}$/', $token)) {
            return null;
        }

        $transaction = LaundryTransaction::query()->where('tracking_token', $token)->first();

        if (! $transaction || ! hash_equals((string) $transaction->tracking_token, $token)) {
            return null;
        }

        return $transaction;
    }

    /** QR hanya tersedia selama transaksi aktif dan QR belum dipakai. */
    public function qrIsAvailable(LaundryTransaction $transaction): bool
    {
        $verification = $transaction->verification;

        return ! in_array($transaction->status, [TransactionStatus::Cancelled, TransactionStatus::Completed], true)
            && ! ($verification?->isUsed() ?? false)
            && ! ($verification?->isExpired() ?? false);
    }

    /**
     * Data untuk halaman publik. Sengaja TIDAK memuat: nomor WhatsApp, catatan,
     * harga, nama karyawan, alasan pembatalan, dan token QR.
     *
     * @return array<string, mixed>
     */
    public function payload(LaundryTransaction $transaction): array
    {
        $transaction->loadMissing(['statusHistories', 'verification']);

        $available = $this->qrIsAvailable($transaction);
        $used = $transaction->status === TransactionStatus::Completed
            || ($transaction->verification?->isUsed() ?? false);

        return [
            'business' => $this->business(),
            'code' => $transaction->code,
            'customer_name' => Privacy::maskName($transaction->customer_name),
            'package_name' => $transaction->package_name,
            'quantity' => (float) $transaction->quantity,
            'unit' => $transaction->package_unit->value,
            'unit_label' => $transaction->package_unit->label(),
            'received_at' => $transaction->received_at?->toIso8601String(),
            'estimated_completion_at' => $transaction->estimated_completion_at?->toIso8601String(),
            'ready_at' => $transaction->ready_at?->toIso8601String(),
            'completed_at' => $transaction->completed_at?->toIso8601String(),
            'status' => $transaction->status->value,
            'status_label' => $transaction->status->label(),
            'status_tone' => $transaction->status->tone(),
            'is_ready' => $transaction->status === TransactionStatus::Ready,
            'is_completed' => $transaction->status === TransactionStatus::Completed,
            'is_cancelled' => $transaction->status === TransactionStatus::Cancelled,
            'pickup' => ['message' => $this->pickupMessage($transaction)],
            'timeline' => $this->timeline($transaction),
            'qr' => [
                'available' => $available,
                'used' => $used,
                'image_url' => $available
                    ? '/api/public/tracking/'.$transaction->tracking_token.'/qr'
                    : null,
            ],
        ];
    }

    /**
     * Timeline dengan status tiap tahap:
     *   done | current | pending | skipped (tahap dilewati, mis. setrika) | inactive | cancelled
     *
     * @return array<int, array<string, mixed>>
     */
    public function timeline(LaundryTransaction $transaction): array
    {
        $reached = [];
        foreach ($transaction->statusHistories as $history) {
            $reached[$history->to_status->value] ??= $history->created_at;
        }

        $flow = TransactionStatus::timeline();
        $cancelled = $transaction->status === TransactionStatus::Cancelled;

        $lastIndex = -1;
        foreach ($flow as $index => $step) {
            if (isset($reached[$step->value])) {
                $lastIndex = $index;
            }
        }

        $steps = [];

        foreach ($flow as $index => $step) {
            $at = $reached[$step->value] ?? null;

            if ($at !== null) {
                $state = ($index === $lastIndex && ! $cancelled && $step !== TransactionStatus::Completed)
                    ? 'current'
                    : 'done';
            } elseif ($index < $lastIndex) {
                $state = 'skipped';
            } else {
                $state = $cancelled ? 'inactive' : 'pending';
            }

            $steps[] = [
                'status' => $step->value,
                'label' => $step->label(),
                'state' => $state,
                'at' => $at?->toIso8601String(),
            ];
        }

        if ($cancelled) {
            $steps[] = [
                'status' => TransactionStatus::Cancelled->value,
                'label' => TransactionStatus::Cancelled->label(),
                'state' => 'cancelled',
                'at' => ($reached[TransactionStatus::Cancelled->value] ?? null)?->toIso8601String(),
            ];
        }

        return $steps;
    }

    private function pickupMessage(LaundryTransaction $transaction): string
    {
        $timezone = config('app.timezone');

        return match ($transaction->status) {
            TransactionStatus::Ready => 'Cucian Anda sudah siap diambil. Tunjukkan QR Code di bawah kepada petugas saat pengambilan.',
            TransactionStatus::Completed => 'Cucian sudah diambil'
                .($transaction->completed_at
                    ? ' pada '.$transaction->completed_at->timezone($timezone)->translatedFormat('d M Y, H:i').' WITA'
                    : '')
                .'. Terima kasih telah menggunakan layanan kami!',
            TransactionStatus::Cancelled => 'Transaksi ini telah dibatalkan. Hubungi kami jika Anda memiliki pertanyaan.',
            default => 'Cucian Anda sedang diproses'
                .($transaction->estimated_completion_at
                    ? '. Estimasi selesai: '.$transaction->estimated_completion_at->timezone($timezone)->translatedFormat('d M Y, H:i').' WITA.'
                    : '.'),
        };
    }

    /** @return array{name: string, whatsapp: string|null} */
    private function business(): array
    {
        $settings = LaundryWebsiteSetting::query()
            ->whereIn('key', ['business_name', 'whatsapp'])
            ->pluck('value', 'key');

        $name = $settings->get('business_name');
        $whatsapp = $settings->get('whatsapp');

        return [
            'name' => filled($name) ? $name : config('app.name'),
            'whatsapp' => filled($whatsapp) ? PhoneNumber::normalize($whatsapp) : null,
        ];
    }
}