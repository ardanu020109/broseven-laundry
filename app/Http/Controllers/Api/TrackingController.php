<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\QRCodeService;
use App\Services\TrackingService;
use App\Support\SvgResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class TrackingController extends Controller
{
    public function __construct(
        private readonly TrackingService $tracking,
        private readonly QRCodeService $qr,
    ) {
    }

    public function show(string $token): JsonResponse
    {
        $transaction = $this->tracking->findByToken($token);

        abort_if($transaction === null, 404);

        return response()
            ->json(['data' => $this->tracking->payload($transaction)])
            ->withHeaders([
                'Cache-Control' => 'no-store',
                'X-Robots-Tag' => 'noindex, nofollow',
            ]);
    }

    public function qr(string $token): Response
    {
        $transaction = $this->tracking->findByToken($token);

        abort_if($transaction === null, 404);

        $verification = $this->qr->ensureVerification($transaction);

        // QR tidak ditampilkan lagi setelah dipakai atau jika transaksi dibatalkan.
        abort_unless($this->tracking->qrIsAvailable($transaction), 404);

        return SvgResponse::make($this->qr->verificationSvg($verification), 'private, max-age=60');
    }
}