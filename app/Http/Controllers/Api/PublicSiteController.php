<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WebsiteSettingService;
use Illuminate\Http\JsonResponse;

class PublicSiteController extends Controller
{
    public function __construct(private readonly WebsiteSettingService $settings)
    {
    }

    /** Seluruh konten website publik. Hanya data yang aman ditampilkan ke umum. */
    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->settings->publicSite()]);
    }
}