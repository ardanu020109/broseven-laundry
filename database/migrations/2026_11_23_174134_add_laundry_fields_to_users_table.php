<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->nullable()->unique()->after('name');
            $table->string('phone', 20)->nullable()->after('email');
            $table->string('avatar_path')->nullable()->after('phone');
            $table->string('role', 20)->default('employee')->index()->after('avatar_path');
            $table->boolean('is_active')->default(true)->index()->after('role');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropIndex(['role']);
            $table->dropIndex(['is_active']);
            $table->dropSoftDeletes();
            $table->dropColumn([
                'username',
                'phone',
                'avatar_path',
                'role',
                'is_active',
                'last_login_at',
                'last_login_ip',
            ]);
        });
    }
};