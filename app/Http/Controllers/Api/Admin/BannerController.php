<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogService;
use App\Support\BannerPresets;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class BannerController extends Controller
{
    /** Daftar ukuran, kualitas, dan batas rendering untuk halaman Banner Promosi. */
    public function presets(): JsonResponse
    {
        Gate::authorize('manage-website');

        return response()->json(['data' => [
            'groups' => BannerPresets::groups(),
            'qualities' => BannerPresets::qualities(),
            'limits' => BannerPresets::limits(),
        ]]);
    }

    /** Mencatat bahwa banner PNG diekspor (gambar dibuat di browser). */
    public function exported(Request $request, ActivityLogService $activity): JsonResponse
    {
        Gate::authorize('manage-website');

        $size = ['required', 'numeric', 'between:'.BannerPresets::MIN_CM.','.BannerPresets::MAX_CM];

        $data = $request->validate([
            'preset' => ['required', Rule::in([...BannerPresets::keys(), 'custom'])],
            'width_cm' => $size,
            'height_cm' => $size,
            'quality' => ['required', Rule::in(BannerPresets::qualityKeys())],
            'package_count' => ['required', 'integer', 'min:1', 'max:50'],
        ], [], [
            'preset' => 'ukuran banner',
            'width_cm' => 'lebar',
            'height_cm' => 'tinggi',
            'quality' => 'kualitas',
            'package_count' => 'jumlah paket',
        ]);

        $label = BannerPresets::find($data['preset'])['label'] ?? 'ukuran khusus';

        $activity->log(
            'website.banner_exported',
            "Mengekspor banner PNG {$label} ({$data['width_cm']} x {$data['height_cm']} cm)",
            null,
            ['preset' => $data['preset'], 'quality' => $data['quality'], 'packages' => $data['package_count']],
            $request->user()
        );

        return response()->json(['message' => 'Ekspor banner dicatat.']);
    }
}