<?php

namespace App\Http\Controllers;

use App\Services\WebsiteSettingService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SpaController extends Controller
{
    /** Menyajikan shell SPA Vue, lengkap dengan warna, judul, dan favicon dari database. */
    public function __invoke(Request $request, WebsiteSettingService $settings): Response
    {
        $response = response()->view('app', ['site' => $settings->shell()]);

        // Halaman yang URL-nya membawa token rahasia: jangan diindeks mesin pencari,
        // jangan bocor ke situs lain lewat header Referer, dan jangan disimpan di cache.
        //
        // PENTING: gunakan "same-origin", BUKAN "no-referrer". Sanctum mengenali request SPA
        // dari header Referer/Origin. Dengan "no-referrer", semua GET ke API dianggap tanpa
        // sesi (401) dan pengguna terlogout.
        if ($request->is('tracking/*', 'verify', 'verify/*')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
            $response->headers->set('Referrer-Policy', 'same-origin');
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}