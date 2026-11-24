<?php

namespace App\Policies;

use App\Models\LaundryPackage;
use App\Models\User;

class LaundryPackagePolicy
{
    private function isStaff(User $user): bool
    {
        return $user->is_active && ($user->isAdmin() || $user->isEmployee());
    }

    private function isAdmin(User $user): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    /** Karyawan perlu melihat paket untuk membuat transaksi. */
    public function viewAny(User $user): bool
    {
        return $this->isStaff($user);
    }

    public function view(User $user, LaundryPackage $package): bool
    {
        return $this->isStaff($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, LaundryPackage $package): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, LaundryPackage $package): bool
    {
        return $this->isAdmin($user);
    }

    public function restore(User $user, LaundryPackage $package): bool
    {
        return $this->isAdmin($user);
    }

    public function forceDelete(User $user, LaundryPackage $package): bool
    {
        return false;
    }
}