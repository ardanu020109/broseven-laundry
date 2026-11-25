<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Pengaturan akun milik pengguna yang sedang login. Selalu bekerja pada akun sendiri.
 */
class AccountService
{
    public function __construct(
        private readonly ActivityLogService $activity,
        private readonly UserSessionService $sessions,
        private readonly AvatarService $avatars,
    ) {
    }

    /** @param  array<string, mixed>  $data  name, email, phone (role tidak pernah diterima) */
    public function updateProfile(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $user->fill(Arr::only($data, ['name', 'email', 'phone']));

            $changed = array_keys($user->getDirty());

            if ($changed !== []) {
                $user->save();

                $this->activity->log('account.profile_updated', 'Memperbarui profil akun', $user, ['fields' => $changed], $user);
            }

            return $user->refresh();
        });
    }

    /** Perangkat lain dikeluarkan; sesi yang sedang dipakai tetap berjalan. */
    public function updatePassword(User $user, string $password, ?string $currentSessionId): void
    {
        DB::transaction(function () use ($user, $password, $currentSessionId) {
            $user->password = $password;
            $user->save();

            $this->sessions->revokeOthers($user, $currentSessionId);

            $this->activity->log('account.password_changed', 'Mengganti password akun', $user, [], $user);
        });
    }

    public function storeAvatar(User $user, UploadedFile $file): User
    {
        $path = $this->avatars->store($file);

        try {
            $previous = DB::transaction(function () use ($user, $path) {
                $old = $user->avatar_path;

                $user->fill(['avatar_path' => $path])->save();
                $this->activity->log('account.avatar_updated', 'Mengganti foto profil', $user, [], $user);

                return $old;
            });
        } catch (Throwable $exception) {
            $this->avatars->delete($path);

            throw $exception;
        }

        $this->avatars->delete($previous);

        return $user->refresh();
    }

    public function removeAvatar(User $user): User
    {
        $previous = $user->avatar_path;

        if ($previous === null) {
            return $user;
        }

        DB::transaction(function () use ($user) {
            $user->fill(['avatar_path' => null])->save();
            $this->activity->log('account.avatar_removed', 'Menghapus foto profil', $user, [], $user);
        });

        $this->avatars->delete($previous);

        return $user->refresh();
    }
}