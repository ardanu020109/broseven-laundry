<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Models\LaundryTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LaundryStatusService
{
    public function __construct(private readonly ActivityLogService $activity)
    {
    }

    /**
     * Mengubah status sesuai workflow. Baris dikunci selama proses sehingga
     * perubahan bersamaan tidak bisa menghasilkan status ganda.
     */
    public function change(
        LaundryTransaction $transaction,
        TransactionStatus $to,
        User $actor,
        ?string $note = null,
    ): LaundryTransaction {
        return DB::transaction(function () use ($transaction, $to, $actor, $note) {
            $locked = LaundryTransaction::query()->lockForUpdate()->findOrFail($transaction->getKey());
            $from = $locked->status;
            $note = filled($note) ? trim($note) : null;

            if ($from->isFinal()) {
                throw ValidationException::withMessages([
                    'status' => 'Transaksi yang sudah selesai atau dibatalkan tidak dapat diubah.',
                ]);
            }

            if (! $from->canTransitionTo($to)) {
                throw ValidationException::withMessages([
                    'status' => "Status tidak dapat diubah dari {$from->label()} ke {$to->label()}.",
                ]);
            }

            if ($to === TransactionStatus::Cancelled && $note === null) {
                throw ValidationException::withMessages([
                    'note' => 'Alasan pembatalan wajib diisi.',
                ]);
            }

            $now = now();
            $attributes = ['status' => $to];

            if ($to === TransactionStatus::Ready) {
                $attributes['ready_at'] = $now;
            }

            if ($to === TransactionStatus::Completed) {
                $attributes['completed_at'] = $now;
                $attributes['completed_by'] = $actor->id;
            }

            if ($to === TransactionStatus::Cancelled) {
                $attributes['cancelled_at'] = $now;
                $attributes['cancel_reason'] = $note;
            }

            $locked->forceFill($attributes)->save();

            // Cucian sudah diambil: QR tidak boleh dipakai lagi.
            if ($to === TransactionStatus::Completed) {
                $locked->verification()->whereNull('used_at')->update([
                    'used_at' => $now,
                    'used_by' => $actor->id,
                ]);
            }

            $locked->statusHistories()->create([
                'from_status' => $from,
                'to_status' => $to,
                'changed_by' => $actor->id,
                'note' => $note,
                'created_at' => $now,
            ]);

            $this->activity->log(
                'transaction.status_changed',
                "Mengubah status {$locked->code}: {$from->label()} menjadi {$to->label()}",
                $locked,
                ['from' => $from->value, 'to' => $to->value, 'note' => $note],
                $actor
            );

            return $locked;
        });
    }

    /** Pengambilan cucian (dipakai juga oleh verifikasi QR di Fase 7). */
    public function complete(LaundryTransaction $transaction, User $actor, ?string $note = null): LaundryTransaction
    {
        return $this->change($transaction, TransactionStatus::Completed, $actor, $note);
    }
}