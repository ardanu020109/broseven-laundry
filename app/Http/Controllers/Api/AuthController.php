<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        abort_unless(
            $request->hasSession(),
            419,
            'Permintaan tidak valid. Muat ulang halaman lalu coba lagi.'
        );

        $user = $request->authenticate();

        Auth::guard('web')->login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        // Catat login terakhir tanpa mengubah updated_at.
        $user->timestamps = false;
        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();
        $user->timestamps = true;

        return response()->json([
            'message' => 'Login berhasil.',
            'data' => new UserResource($user),
        ]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => 'Logout berhasil.']);
    }
}