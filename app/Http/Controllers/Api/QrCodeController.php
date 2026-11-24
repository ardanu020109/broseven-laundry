<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LaundryTransaction;
use App\Services\QRCodeService;
use App\Support\SvgResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class QrCodeController extends Controller
{
    public function __construct(private readonly QRCodeService $qr)
    {
    }

    /** Gambar QR untuk detail transaksi dan cetak bukti (khusus staff). */
    public function show(LaundryTransaction $transaction): Response
    {
        Gate::authorize('view', $transaction);

        $verification = $this->qr->ensureVerification($transaction);

        return SvgResponse::make($this->qr->verificationSvg($verification));
    }
}