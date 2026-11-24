<?php

namespace App\Console\Commands;

use App\Enums\TransactionStatus;
use App\Models\LaundryPackage;
use App\Models\LaundryTransaction;
use App\Models\User;
use App\Services\LaundryStatusService;
use App\Services\QRCodeService;
use App\Services\TransactionService;
use App\Support\AppUrl;
use Illuminate\Console\Command;

class ShowTransactionLinks extends Command
{
    protected $signature = 'broseven:links
        {code? : Kode transaksi tertentu}
        {--status= : Filter status, mis. READY}
        {--limit=5 : Jumlah transaksi}
        {--make-ready : Buat transaksi demo berstatus READY}';

    protected $description = 'Menampilkan link tracking dan verifikasi QR (khusus lokal)';

    public function handle(QRCodeService $qr): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Perintah ini hanya untuk lingkungan lokal.');

            return self::FAILURE;
        }

        $code = $this->argument('code');

        if ($this->option('make-ready')) {
            $code = $this->makeReadyTransaction();

            if ($code === null) {
                return self::FAILURE;
            }
        }

        $query = LaundryTransaction::query()->latest('received_at');

        if ($code) {
            $query->where('code', strtoupper($code));
        }

        if ($status = $this->option('status')) {
            $query->where('status', strtoupper($status));
        }

        $transactions = $query->limit((int) $this->option('limit'))->get();

        if ($transactions->isEmpty()) {
            $this->warn('Tidak ada transaksi yang cocok.');

            return self::SUCCESS;
        }

        foreach ($transactions as $transaction) {
            $verification = $qr->ensureVerification($transaction);

            $this->newLine();
            $this->info("{$transaction->code}  [{$transaction->status->value}]  {$transaction->customer_name}");
            $this->line('  Tracking  : '.AppUrl::to('/tracking/'.$transaction->tracking_token));
            $this->line('  Verifikasi: '.$qr->verificationUrl($verification));
        }

        return self::SUCCESS;
    }

    private function makeReadyTransaction(): ?string
    {
        $user = User::query()->active()->first();
        $package = LaundryPackage::query()->active()->ordered()->first();

        if (! $user || ! $package) {
            $this->error('Dibutuhkan minimal 1 user aktif dan 1 paket aktif. Jalankan: php artisan db:seed');

            return null;
        }

        $transaction = app(TransactionService::class)->create([
            'customer_name' => 'Tes Verifikasi',
            'customer_phone' => '6281234567890',
            'package_id' => $package->id,
            'quantity' => 2,
        ], $user);

        foreach ([TransactionStatus::Washing, TransactionStatus::Drying, TransactionStatus::Ready] as $status) {
            $transaction = app(LaundryStatusService::class)->change($transaction, $status, $user);
        }

        $this->info("Transaksi demo {$transaction->code} dibuat dengan status READY.");

        return $transaction->code;
    }
}