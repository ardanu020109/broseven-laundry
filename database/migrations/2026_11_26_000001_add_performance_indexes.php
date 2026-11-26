<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pembersihan dan pengurutan log aktivitas.
        Schema::table('laundry_activity_logs', function (Blueprint $table) {
            $table->index('created_at', 'lal_created_at_idx');
        });

        // Notifikasi "melewati estimasi" dan filter status.
        Schema::table('laundry_transactions', function (Blueprint $table) {
            $table->index(['status', 'estimated_completion_at'], 'lt_status_eta_idx');
        });
    }

    public function down(): void
    {
        Schema::table('laundry_activity_logs', function (Blueprint $table) {
            $table->dropIndex('lal_created_at_idx');
        });

        Schema::table('laundry_transactions', function (Blueprint $table) {
            $table->dropIndex('lt_status_eta_idx');
        });
    }
};