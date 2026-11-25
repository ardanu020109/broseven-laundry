<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Mengakhiri sesi login pengguna (tabel sessions), token API, dan "ingat saya".
 */
class UserSessionService
{
    /** Semua sesi diakhiri (penonaktifan akun, reset password oleh admin). */
    public function revokeAll(User $user): void
    {
        $this->deleteSessions($user, null);
        $user->tokens()->delete();
        $this->rotateRememberToken($user);
    }

    /** Semua sesi kecuali yang sedang dipakai (ganti password sendiri). */
    public function revokeOthers(User $user, ?string $currentSessionId): void
    {
        $this->deleteSessions($user, $currentSessionId);
        $user->tokens()->delete();
        $this->rotateRememberToken($user);
    }

    private function deleteSessions(User $user, ?string $except): void
    {
        $table = (string) config('session.table', 'sessions');

        if (! Schema::hasTable($table)) {
            return;
        }

        DB::table($table)
            ->where('user_id', $user->getKey())
            ->when($except, fn ($query) => $query->where('id', '!=', $except))
            ->delete();
    }

    private function rotateRememberToken(User $user): void
    {
        $user->forceFill(['remember_token' => Str::random(60)])->save();
    }
}