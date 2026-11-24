<?php

namespace Database\Seeders;

use App\Enums\PackageUnit;
use App\Enums\TransactionStatus;
use App\Models\LaundryPackage;
use App\Models\LaundryTransaction;
use App\Models\LaundryTransactionStatusHistory;
use App\Models\LaundryTransactionVerification;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Data demo untuk pengembangan. TIDAK pernah berjalan di production.
 */
class LocalDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $this->seedPackages();
        $this->seedTransactions();
    }

    private function seedPackages(): void
    {
        $packages = [
            [
                'name' => 'Cuci Lipat Reguler',
                'description' => 'Cuci bersih, kering, dan dilipat rapi.',
                'price' => 6000,
                'unit' => PackageUnit::Kg,
                'estimated_hours' => 48,
                'features' => ['Deterjen berkualitas', 'Pewangi', 'Dilipat rapi'],
                'sort_order' => 1,
            ],
            [
                'name' => 'Cuci Setrika Reguler',
                'description' => 'Cuci bersih lalu disetrika rapi.',
                'price' => 8000,
                'unit' => PackageUnit::Kg,
                'estimated_hours' => 48,
                'features' => ['Deterjen berkualitas', 'Pewangi', 'Disetrika', 'Dikemas rapi'],
                'sort_order' => 2,
            ],
            [
                'name' => 'Express 24 Jam',
                'description' => 'Layanan kilat selesai dalam satu hari.',
                'price' => 12000,
                'unit' => PackageUnit::Kg,
                'estimated_hours' => 24,
                'features' => ['Prioritas pengerjaan', 'Disetrika', 'Dikemas rapi'],
                'sort_order' => 3,
            ],
            [
                'name' => 'Bedcover dan Selimut',
                'description' => 'Perawatan khusus untuk bedcover dan selimut tebal.',
                'price' => 30000,
                'unit' => PackageUnit::Item,
                'estimated_hours' => 72,
                'features' => ['Cuci khusus', 'Pengeringan menyeluruh', 'Dikemas plastik'],
                'sort_order' => 4,
            ],
        ];

        foreach ($packages as $data) {
            LaundryPackage::firstOrCreate(['name' => $data['name']], $data + ['is_active' => true]);
        }
    }

    private function seedTransactions(): void
    {
        if (LaundryTransaction::query()->exists()) {
            return;
        }

        $users = User::query()->active()->get();
        $packages = LaundryPackage::query()->active()->get();

        if ($users->isEmpty() || $packages->isEmpty()) {
            return;
        }

        $path = TransactionStatus::timeline(); // RECEIVED ... COMPLETED (indeks 0-5)
        $sequence = [];

        for ($i = 0; $i < 30; $i++) {
            $package = $packages->random();
            $daysAgo = random_int(0, 40);

            $receivedAt = now()->subDays($daysAgo)->setTime(random_int(8, 19), random_int(0, 59), 0);
            if ($receivedAt->isFuture()) {
                $receivedAt = now()->subMinutes(random_int(5, 90));
            }

            $dayKey = $receivedAt->format('ymd');
            $sequence[$dayKey] = ($sequence[$dayKey] ?? 0) + 1;

            $isCancelled = random_int(1, 100) <= 7;
            $reachedIndex = $daysAgo >= 5
                ? (random_int(1, 10) <= 8 ? 5 : 4)
                : random_int(0, 5);

            if ($isCancelled) {
                $reachedIndex = random_int(0, 2);
            }

            $receiver = $users->random();

            $transaction = LaundryTransaction::factory()
                ->forPackage($package)
                ->create([
                    'code' => sprintf('BS-%s-%04d', $dayKey, $sequence[$dayKey]),
                    'quantity' => number_format(random_int(10, 80) / 10, 2, '.', ''),
                    'received_at' => $receivedAt,
                    'estimated_completion_at' => $receivedAt->copy()->addHours($package->estimated_hours),
                    'received_by' => $receiver->id,
                ]);

            // Histori: status awal, lalu setiap tahap sampai status akhir.
            $time = $receivedAt->copy();
            $stamps = [0 => $time->copy()];
            $actor = $receiver;
            $previous = $path[0];

            $this->recordHistory($transaction, null, $path[0], $time, $receiver->id);

            for ($step = 1; $step <= $reachedIndex; $step++) {
                $time = $time->copy()->addHours(random_int(1, 10))->addMinutes(random_int(0, 59));
                if ($time->isFuture()) {
                    $time = now();
                }

                $actor = $users->random();
                $this->recordHistory($transaction, $previous, $path[$step], $time, $actor->id);

                $stamps[$step] = $time->copy();
                $previous = $path[$step];
            }

            $attributes = ['status' => $path[$reachedIndex]];

            if ($reachedIndex >= 4) {
                $attributes['ready_at'] = $stamps[4];
            }

            if ($reachedIndex === 5) {
                $attributes['completed_at'] = $stamps[5];
                $attributes['completed_by'] = $actor->id;
            }

            if ($isCancelled) {
                $time = $time->copy()->addMinutes(random_int(10, 120));
                if ($time->isFuture()) {
                    $time = now();
                }

                $this->recordHistory(
                    $transaction,
                    $previous,
                    TransactionStatus::Cancelled,
                    $time,
                    $actor->id,
                    'Dibatalkan oleh pelanggan (data demo)'
                );

                $attributes = array_merge($attributes, [
                    'status' => TransactionStatus::Cancelled,
                    'cancelled_at' => $time,
                    'cancel_reason' => 'Dibatalkan oleh pelanggan (data demo)',
                ]);
            }

            $transaction->forceFill($attributes)->save();

            LaundryTransactionVerification::create([
                'transaction_id' => $transaction->id,
                'token' => Str::random(48),
                'used_at' => $attributes['completed_at'] ?? null,
                'used_by' => $attributes['completed_by'] ?? null,
                'scan_count' => isset($attributes['completed_at']) ? 1 : 0,
            ]);
        }
    }

    private function recordHistory(
        LaundryTransaction $transaction,
        ?TransactionStatus $from,
        TransactionStatus $to,
        Carbon $at,
        int $userId,
        ?string $note = null,
    ): void {
        (new LaundryTransactionStatusHistory())->forceFill([
            'transaction_id' => $transaction->id,
            'from_status' => $from,
            'to_status' => $to,
            'changed_by' => $userId,
            'note' => $note,
            'created_at' => $at,
        ])->save();
    }
}