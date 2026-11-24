<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $config = config('broseven.admin');

        if (blank($config['password'])) {
            $this->command?->error('ADMIN_PASSWORD belum diisi di .env. Seeder dibatalkan.');

            return;
        }

        // Admin awal. Password hanya diisi saat akun baru dibuat,
        // sehingga seeding ulang tidak mereset password yang sudah diganti.
        $admin = User::withTrashed()->firstOrNew(['email' => $config['email']]);

        if (! $admin->exists) {
            $admin->password = $config['password'];
        }

        $admin->forceFill([
            'name' => $config['name'],
            'username' => $config['username'],
            'phone' => $config['phone'],
            'role' => UserRole::Admin,
            'is_active' => true,
            'email_verified_at' => $admin->email_verified_at ?? now(),
        ])->save();

        if ($admin->trashed()) {
            $admin->restore();
        }

        // Akun demo hanya untuk lingkungan lokal, tidak dibuat di production.
        if (app()->environment('local')) {
            $employee = User::withTrashed()->firstOrNew(['email' => 'karyawan@broseven.test']);

            if (! $employee->exists) {
                $employee->password = 'Karyawan@2026';
            }

            $employee->forceFill([
                'name' => 'Karyawan Demo',
                'username' => 'karyawan',
                'role' => UserRole::Employee,
                'is_active' => true,
                'email_verified_at' => $employee->email_verified_at ?? now(),
            ])->save();
        }
    }
}