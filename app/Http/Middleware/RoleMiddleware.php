<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class RoleMiddleware
{
    /**
     * Pemakaian di route:
     *   ->middleware('role:admin')
     *   ->middleware('role:admin,employee')
     *
     * Nama role yang tidak dikenal diabaikan, sehingga akses ditolak (fail closed).
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            throw new AuthenticationException();
        }

        $allowed = array_filter(
            array_map(fn (string $role) => UserRole::tryFrom($role), $roles)
        );

        if (! in_array($user->role, $allowed, true)) {
            throw new AccessDeniedHttpException('Anda tidak memiliki izin untuk mengakses fitur ini.');
        }

        return $next($request);
    }
}