<?php

namespace App\Policies;

use App\Models\User;

/**
 * Policy untuk modul "Kelola Karyawan" (khusus admin).
 * Perubahan profil sendiri ditangani modul Pengaturan Akun, bukan policy ini.
 */
class UserPolicy
{
    private function isAdmin(User $user): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, User $model): bool
    {
        return $this->isAdmin($user) || ($user->is_active && $user->is($model));
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    /** Admin hanya mengelola akun berole karyawan. */
    public function update(User $user, User $model): bool
    {
        return $this->isAdmin($user) && $model->isEmployee();
    }

    public function delete(User $user, User $model): bool
    {
        return $this->isAdmin($user) && $model->isEmployee() && ! $user->is($model);
    }

    public function restore(User $user, User $model): bool
    {
        return $this->isAdmin($user) && $model->isEmployee();
    }

    public function forceDelete(User $user, User $model): bool
    {
        return false;
    }
}