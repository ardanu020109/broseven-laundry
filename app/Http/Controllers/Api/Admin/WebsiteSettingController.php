<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Website\ResetColorsRequest;
use App\Http\Requests\Website\UpdateColorsRequest;
use App\Http\Requests\Website\UpdateWebsiteSettingsRequest;
use App\Http\Requests\Website\UploadBrandingImageRequest;
use App\Services\WebsiteSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class WebsiteSettingController extends Controller
{
    public function __construct(private readonly WebsiteSettingService $settings)
    {
    }

    public function show(): JsonResponse
    {
        Gate::authorize('manage-website');

        return response()->json([
            'data' => [
                'settings' => $this->settings->settings(),
                'colors' => $this->settings->colorItems(),
            ],
        ]);
    }

    public function update(UpdateWebsiteSettingsRequest $request): JsonResponse
    {
        return response()->json([
            'message' => 'Pengaturan website berhasil disimpan.',
            'data' => $this->settings->update($request->payload(), $request->user()),
        ]);
    }

    public function uploadImage(UploadBrandingImageRequest $request, string $kind): JsonResponse
    {
        $data = $this->settings->storeImage($kind, $request->file('image'), $request->user());

        return response()->json([
            'message' => $kind === 'logo' ? 'Logo berhasil diperbarui.' : 'Favicon berhasil diperbarui.',
            'data' => $data,
        ]);
    }

    public function deleteImage(Request $request, string $kind): JsonResponse
    {
        Gate::authorize('manage-website');

        return response()->json([
            'message' => $kind === 'logo' ? 'Logo dihapus.' : 'Favicon dihapus.',
            'data' => $this->settings->removeImage($kind, $request->user()),
        ]);
    }

    public function updateColors(UpdateColorsRequest $request): JsonResponse
    {
        return response()->json([
            'message' => 'Warna website berhasil disimpan.',
            'data' => $this->settings->updateColors($request->payload(), $request->user()),
        ]);
    }

    public function resetColors(ResetColorsRequest $request): JsonResponse
    {
        return response()->json([
            'message' => 'Warna dikembalikan ke default.',
            'data' => $this->settings->resetColors($request->keysToReset(), $request->user()),
        ]);
    }
}