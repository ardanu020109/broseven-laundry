<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Models\LaundryPackage;
use App\Models\LaundryTransaction;
use App\Models\User;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TransactionService
{
    private const CODE_PREFIX = 'BS';

    private const MAX_ATTEMPTS = 3;

    public function __construct(private readonly ActivityLogService $activity)
    {
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $status = $filters['status'] ?? null;

        return LaundryTransaction::query()
            ->with('receiver')
            ->search($filters['search'] ?? null)
            ->when($status === 'IN_PROGRESS', fn (Builder $q) => $q->inProgress())
            ->when($status && $status !== 'IN_PROGRESS', fn (Builder $q) => $q->withStatus($status))
            ->when(
                ! empty($filters['date_from']),
                fn (Builder $q) => $q->where('received_at', '>=', Carbon::parse($filters['date_from'])->startOfDay())
            )
            ->when(
                ! empty($filters['date_to']),
                fn (Builder $q) => $q->where('received_at', '<=', Carbon::parse($filters['date_to'])->endOfDay())
            )
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    /**
     * Membuat transaksi baru. Harga, total, status, kode, dan token
     * seluruhnya ditentukan server, bukan input pengguna.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): LaundryTransaction
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(fn () => $this->persist($data, $actor));
            } catch (UniqueConstraintViolationException $e) {
                // Kode/token bentrok akibat dua transaksi bersamaan: ulangi.
                if ($attempt >= self::MAX_ATTEMPTS) {
                    throw $e;
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persist(array $data, User $actor): LaundryTransaction
    {
        $package = LaundryPackage::query()->active()->find($data['package_id']);

        if (! $package) {
            throw ValidationException::withMessages([
                'package_id' => 'Paket laundry tidak tersedia.',
            ]);
        }

        $now = now();
        $quantity = Money::decimal($data['quantity']);

        $estimated = filled($data['estimated_completion_at'] ?? null)
            ? $this->parseDate($data['estimated_completion_at'])
            : $now->copy()->addHours($package->estimated_hours);

        $transaction = new LaundryTransaction();
        $transaction->forceFill([
            'code' => $this->generateCode($now),
            'tracking_token' => Str::random(40),
            'customer_name' => $data['customer_name'],
            'customer_phone' => $data['customer_phone'],
            'package_id' => $package->id,
            'package_name' => $package->name,
            'package_unit' => $package->unit,
            'package_price' => $package->price,
            'quantity' => $quantity,
            'total_price' => Money::multiply((string) $package->price, $quantity),
            'status' => TransactionStatus::Received,
            'notes' => $data['notes'] ?? null,
            'received_at' => $now,
            'estimated_completion_at' => $estimated,
            'received_by' => $actor->id,
        ])->save();

        $transaction->statusHistories()->create([
            'from_status' => null,
            'to_status' => TransactionStatus::Received,
            'changed_by' => $actor->id,
            'note' => 'Cucian diterima',
            'created_at' => $now,
        ]);

        // Token QR verifikasi: satu per transaksi (dipakai di Fase 7).
        $transaction->verification()->create([
            'token' => Str::random(48),
        ]);

        $this->activity->log(
            'transaction.created',
            "Membuat transaksi {$transaction->code}",
            $transaction,
            [
                'package' => $package->name,
                'quantity' => $quantity,
                'total_price' => $transaction->total_price,
            ],
            $actor
        );

        return $transaction;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(LaundryTransaction $transaction, array $data, User $actor): LaundryTransaction
    {
        return DB::transaction(function () use ($transaction, $data, $actor) {
            $locked = LaundryTransaction::query()->lockForUpdate()->findOrFail($transaction->getKey());

            if ($locked->status->isFinal()) {
                throw ValidationException::withMessages([
                    'transaction' => 'Transaksi yang sudah selesai atau dibatalkan tidak dapat diubah.',
                ]);
            }

            $packageChanged = isset($data['package_id'])
                && (int) $data['package_id'] !== (int) $locked->package_id;

            $newQuantity = isset($data['quantity']) ? Money::decimal($data['quantity']) : null;

            $quantityChanged = $newQuantity !== null
                && bccomp($newQuantity, Money::decimal($locked->quantity), 2) !== 0;

            if (($packageChanged || $quantityChanged) && $locked->status !== TransactionStatus::Received) {
                throw ValidationException::withMessages([
                    'transaction' => 'Paket dan berat/jumlah cucian hanya dapat diubah selama status masih Diterima.',
                ]);
            }

            $locked->fill(Arr::only($data, ['customer_name', 'customer_phone', 'notes']));

            if (filled($data['estimated_completion_at'] ?? null)) {
                $locked->estimated_completion_at = $this->parseDate($data['estimated_completion_at']);
            }

            if ($packageChanged) {
                $package = LaundryPackage::query()->active()->find($data['package_id']);

                if (! $package) {
                    throw ValidationException::withMessages([
                        'package_id' => 'Paket laundry tidak tersedia.',
                    ]);
                }

                // Paket berganti: snapshot diperbarui dengan harga paket saat ini.
                $locked->forceFill([
                    'package_id' => $package->id,
                    'package_name' => $package->name,
                    'package_unit' => $package->unit,
                    'package_price' => $package->price,
                ]);
            }

            if ($packageChanged || $quantityChanged) {
                $quantity = $newQuantity ?? Money::decimal($locked->quantity);

                $locked->forceFill([
                    'quantity' => $quantity,
                    'total_price' => Money::multiply((string) $locked->package_price, $quantity),
                ]);
            }

            $changes = [];
            foreach ($locked->getDirty() as $key => $value) {
                $changes[$key] = ['from' => $locked->getRawOriginal($key), 'to' => $value];
            }

            if ($changes !== []) {
                $locked->save();

                $this->activity->log(
                    'transaction.updated',
                    "Mengubah transaksi {$locked->code}",
                    $locked,
                    ['changes' => $changes],
                    $actor
                );
            }

            return $locked;
        });
    }

    /** Soft delete. Transaksi COMPLETED tidak boleh dihapus. */
    public function delete(LaundryTransaction $transaction, User $actor): void
    {
        DB::transaction(function () use ($transaction, $actor) {
            $locked = LaundryTransaction::query()->lockForUpdate()->findOrFail($transaction->getKey());

            if ($locked->status === TransactionStatus::Completed) {
                throw ValidationException::withMessages([
                    'transaction' => 'Transaksi yang sudah selesai tidak dapat dihapus.',
                ]);
            }

            $locked->delete();

            $this->activity->log(
                'transaction.deleted',
                "Menghapus transaksi {$locked->code}",
                $locked,
                ['status' => $locked->status->value],
                $actor
            );
        });
    }

    /** Kode harian berurutan: BS-261124-0001. Dipanggil di dalam DB transaction. */
    private function generateCode(Carbon $now): string
    {
        $prefix = self::CODE_PREFIX.'-'.$now->format('ymd').'-';

        $last = LaundryTransaction::withTrashed()
            ->where('code', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('code')
            ->value('code');

        $next = $last ? ((int) Str::afterLast($last, '-')) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function parseDate(mixed $value): Carbon
    {
        return Carbon::parse($value)->setTimezone(config('app.timezone'));
    }
}