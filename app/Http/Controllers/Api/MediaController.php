<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MediaController extends Controller
{
    private const MIME_TYPES = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
    ];

    /**
     * Menyajikan logo/favicon. Nama file dibatasi di route (40 karakter acak + ekstensi),
     * sehingga tidak ada celah path traversal dan tidak perlu storage:link.
     */
    public function show(string $file): BinaryFileResponse
    {
        $path = 'branding/'.$file;
        $disk = Storage::disk('public');

        abort_unless($disk->exists($path), 404);

        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        return response()->file($disk->path($path), [
            'Content-Type' => self::MIME_TYPES[$extension] ?? 'application/octet-stream',
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }
}