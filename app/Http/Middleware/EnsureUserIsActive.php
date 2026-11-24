<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Dijalankan pada setiap request terautentikasi. Jika admin menonaktifkan
     * akun saat pengguna masih login, sesinya langsung diakhiri.
     * (Akun yang di-soft-delete sudah otomatis dianggap tidak login oleh Laravel.)
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            Auth::guard('web')->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return response()->json([
                'message' => 'Akun Anda dinonaktifkan. Silakan hubungi admin.',
                'code' => 'ACCOUNT_DISABLED',
            ], 401);
        }

        return $next($request);
    }
}