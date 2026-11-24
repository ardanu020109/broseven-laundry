<?php

namespace App\Policies;

use App\Enums\TransactionStatus;
use App\Models\LaundryTransaction;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class LaundryTransactionPolicy
{
    private function isStaff(User $user): bool
    {
        return $user->is_active && ($user->isAdmin() || $user->isEmployee());
    }

    private function isAdmin(User $user): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    public function viewAny(User $user): bool
    {
        return $this->isStaff($user);
    }

    public function view(User $user, LaundryTransaction $transaction): bool
    {
        return $this->isStaff($user);
    }

    public function create(User $user): bool
    {
        return $this->isStaff($user);
    }

    /** Edit data transaksi: hanya selama status belum final. */
    public function update(User $user, LaundryTransaction $transaction): Response|bool
    {
        if (! $this->isStaff($user)) {
            return false;
        }

        return $transaction->status->isFinal()
            ? Response::deny('Transaksi yang sudah selesai atau dibatalkan tidak dapat diubah.')
            : true;
    }

    /** Ubah status (termasuk pembatalan dan pengambilan): hanya selama belum final. */
    public function changeStatus(User $user, LaundryTransaction $transaction): Response|bool
    {
        if (! $this->isStaff($user)) {
            return false;
        }

        return $transaction->status->isFinal()
            ? Response::deny('Transaksi yang sudah selesai atau dibatalkan tidak dapat diubah.')
            : true;
    }

    /** Hapus (soft delete): hanya admin, dan transaksi COMPLETED tidak boleh dihapus. */
    public function delete(User $user, LaundryTransaction $transaction): Response|bool
    {
        if (! $this->isAdmin($user)) {
            return false;
        }

        return $transaction->status === TransactionStatus::Completed
            ? Response::deny('Transaksi yang sudah selesai tidak dapat dihapus.')
            : true;
    }

    public function restore(User $user, LaundryTransaction $transaction): bool
    {
        return $this->isAdmin($user);
    }

    /** Penghapusan permanen tidak pernah diizinkan. */
    public function forceDelete(User $user, LaundryTransaction $transaction): bool
    {
        return false;
    }
}