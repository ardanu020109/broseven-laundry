<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $mode = (string) config('broseven.security.csp', 'report-only');
        $document = ! $request->is('api/*', 'sanctum/*');
        $useCsp = $document && in_array($mode, ['report-only', 'enforce'], true) && ! Vite::isRunningHot();

        // Nonce dibuat SEBELUM halaman dirender agar masuk ke tag inline dan @vite.
        $nonce = $useCsp ? ((string) Vite::useCspNonce() ?: null) : null;

        $response = $next($request);
        $headers = $response->headers;

        $defaults = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin', // halaman bertoken memakai "same-origin"
            // camera=(self): pemindai QR memakai kamera.
            'Permissions-Policy' => 'camera=(self), microphone=(), geolocation=(), payment=()',
        ];

        foreach ($defaults as $name => $value) {
            if (! $headers->has($name)) {
                $headers->set($name, $value);
            }
        }

        if ($request->isSecure() && app()->isProduction()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        $type = (string) $headers->get('Content-Type');

        if ($useCsp && ($type === '' || str_contains($type, 'html'))) {
            $headers->set(
                $mode === 'enforce' ? 'Content-Security-Policy' : 'Content-Security-Policy-Report-Only',
                $this->policy($nonce)
            );
        }

        return $response;
    }

    private function policy(?string $nonce): string
    {
        $n = $nonce ? " 'nonce-{$nonce}'" : '';

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self'{$n}",
            "style-src 'self'{$n}",
            "style-src-attr 'unsafe-inline'",
            "img-src 'self' data: blob:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "media-src 'self' blob:",
            "worker-src 'self' blob:",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ]);
    }
}