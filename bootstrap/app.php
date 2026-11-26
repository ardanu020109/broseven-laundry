<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // SPA satu domain: sesi berbasis cookie + proteksi CSRF lewat Sanctum.
        $middleware->statefulApi();

        // Header keamanan untuk semua respons.
        $middleware->append(SecurityHeaders::class);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'active' => EnsureUserIsActive::class,
        ]);

        // Otorisasi harus berjalan SEBELUM route model binding: login -> akun aktif -> role -> binding.
        $middleware->prependToPriorityList(SubstituteBindings::class, EnsureUserIsActive::class);
        $middleware->prependToPriorityList(SubstituteBindings::class, RoleMiddleware::class);

        // Pengaman untuk route web non-API yang memakai middleware auth.
        $middleware->redirectGuestsTo('/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Semua error di bawah /api selalu berbentuk JSON.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $e) => $request->is('api/*') || $request->expectsJson()
        );

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Sesi Anda berakhir atau Anda belum login. Silakan login kembali.',
                ], 401);
            }
        });

        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $message = $e->getMessage();

                if ($message === '' || $message === 'This action is unauthorized.') {
                    $message = 'Anda tidak memiliki izin untuk melakukan aksi ini.';
                }

                return response()->json(['message' => $message], 403);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Data atau alamat yang diminta tidak ditemukan.',
                ], 404);
            }
        });

        $exceptions->render(function (TooManyRequestsHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Terlalu banyak permintaan. Silakan coba lagi beberapa saat lagi.',
                ], 429, $e->getHeaders());
            }
        });
    })->create();

// Dibaca dari config() SETELAH aplikasi boot: env() tidak boleh dipakai di sini karena config cache.
$app->booted(function () {
    $proxies = config('broseven.security.trusted_proxies');

    if (is_string($proxies) && trim($proxies) !== '') {
        TrustProxies::at(trim($proxies) === '*' ? '*' : array_map('trim', explode(',', $proxies)));
    }
});

return $app;