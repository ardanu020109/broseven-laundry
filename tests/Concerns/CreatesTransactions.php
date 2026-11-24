<?php

namespace Tests\Concerns;

use App\Enums\TransactionStatus;
use App\Models\LaundryPackage;
use App\Models\LaundryTransaction;
use App\Models\User;
use App\Services\LaundryStatusService;
use App\Services\TransactionService;

trait CreatesTransactions
{
    /**
     * Membuat transaksi lewat service (test client tetap belum login), lalu
     * menjalankannya melalui urutan status yang diberikan.
     *
     * @param  array<int, TransactionStatus>  $statuses
     */
    protected function makeTransaction(array $statuses = []): LaundryTransaction
    {
        $staff = User::factory()->create();
        $package = LaundryPackage::factory()->create(['price' => 8000, 'estimated_hours' => 48]);

        $transaction = app(TransactionService::class)->create([
            'customer_name' => 'Budi Santoso',
            'customer_phone' => '6281234567890',
            'package_id' => $package->id,
            'quantity' => 2.5,
            'notes' => 'Catatan internal rahasia',
        ], $staff);

        foreach ($statuses as $status) {
            $note = $status === TransactionStatus::Cancelled ? 'Dibatalkan customer' : null;
            $transaction = app(LaundryStatusService::class)->change($transaction, $status, $staff, $note);
        }

        return $transaction->fresh(['verification']);
    }

    protected function readyTransaction(): LaundryTransaction
    {
        return $this->makeTransaction([
            TransactionStatus::Washing,
            TransactionStatus::Drying,
            TransactionStatus::Ready,
        ]);
    }
}