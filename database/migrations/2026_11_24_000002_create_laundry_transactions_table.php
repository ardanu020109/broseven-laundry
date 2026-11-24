<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laundry_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 24)->unique();
            $table->string('tracking_token', 64)->unique();

            $table->string('customer_name', 100);
            $table->string('customer_phone', 20);

            $table->foreignId('package_id')
                ->constrained('laundry_packages')
                ->restrictOnDelete();

            // Snapshot paket saat transaksi dibuat.
            $table->string('package_name', 100);
            $table->string('package_unit', 20);
            $table->decimal('package_price', 12, 2);

            $table->decimal('quantity', 8, 2);
            $table->decimal('total_price', 14, 2);

            $table->string('status', 20)->default('RECEIVED');
            $table->text('notes')->nullable();

            $table->dateTime('received_at');
            $table->dateTime('estimated_completion_at')->nullable();
            $table->dateTime('ready_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();

            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('received_at');
            $table->index('completed_at');
            $table->index('customer_phone');
            $table->index(['status', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laundry_transactions');
    }
};