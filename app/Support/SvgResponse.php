<?php

namespace App\Support;

use Illuminate\Http\Response;

class SvgResponse
{
    /**
     * Respons gambar SVG dengan header pengaman
     * (tidak boleh menjalankan skrip, tidak boleh di-sniff).
     */
    public static function make(string $svg, string $cacheControl = 'private, max-age=300'): Response
    {
        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml; charset=utf-8',
            'Cache-Control' => $cacheControl,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
        ]);
    }
}