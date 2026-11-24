<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laundry_transaction_verifications', function (Blueprint $table) {
            $table->id();
            // unique: satu transaksi hanya memiliki satu QR verifikasi.
            $table->foreignId('transaction_id')
                ->unique()
                ->constrained('laundry_transactions')
                ->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('used_at')->nullable();
            $table->foreignId('used_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('scan_count')->default(0);
            $table->dateTime('last_scanned_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laundry_transaction_verifications');
    }
};