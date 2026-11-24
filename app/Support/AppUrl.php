<?php

namespace App\Support;

class AppUrl
{
    /**
     * URL absolut berdasarkan APP_URL (bukan header Host dari request),
     * dipakai untuk link tracking dan isi QR Code.
     */
    public static function to(string $path): string
    {
        return rtrim((string) config('app.url'), '/').'/'.ltrim($path, '/');
    }
}