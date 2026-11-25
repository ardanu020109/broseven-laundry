<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UpdatePasswordRequest;
use App\Http\Requests\Account\UpdateProfileRequest;
use App\Http\Requests\Account\UploadAvatarRequest;
use App\Http\Resources\UserResource;
use App\Services\AccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Semua aksi bekerja pada akun yang sedang login ($request->user()); tidak ada ID di URL.
 */
class AccountController extends Controller
{
    public function __construct(private readonly AccountService $account)
    {
    }

    public function show(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->account->updateProfile($request->user(), $request->payload());

        return response()->json([
            'message' => 'Profil berhasil diperbarui.',
            'data' => (new UserResource($user))->resolve($request),
        ]);
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $sessionId = $request->hasSession() ? $request->session()->getId() : null;

        $this->account->updatePassword($request->user(), $request->validated('password'), $sessionId);

        return response()->json([
            'message' => 'Password berhasil diganti. Perangkat lain telah dikeluarkan.',
        ]);
    }

    public function uploadAvatar(UploadAvatarRequest $request): JsonResponse
    {
        $user = $this->account->storeAvatar($request->user(), $request->file('photo'));

        return response()->json([
            'message' => 'Foto profil berhasil diperbarui.',
            'data' => (new UserResource($user))->resolve($request),
        ]);
    }

    public function deleteAvatar(Request $request): JsonResponse
    {
        $user = $this->account->removeAvatar($request->user());

        return response()->json([
            'message' => 'Foto profil dihapus.',
            'data' => (new UserResource($user))->resolve($request),
        ]);
    }
}