<?php

namespace App\Services;

use App\Models\LaundryTransaction;
use App\Models\LaundryTransactionVerification;
use App\Support\AppUrl;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

class QRCodeService
{
    /**
     * Mengambil verifikasi transaksi; dibuat otomatis jika belum ada
     * (misalnya data lama). Satu transaksi hanya punya satu verifikasi.
     */
    public function ensureVerification(LaundryTransaction $transaction): LaundryTransactionVerification
    {
        $verification = $transaction->verification;

        if ($verification) {
            return $verification;
        }

        try {
            $verification = $transaction->verification()->create([
                'token' => Str::random(48),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Dibuat bersamaan oleh proses lain.
            $verification = $transaction->verification()->first();
        }

        $transaction->setRelation('verification', $verification);

        return $verification;
    }

    /** Isi QR: hanya URL dengan token acak, tanpa data sensitif. */
    public function verificationUrl(LaundryTransactionVerification $verification): string
    {
        return AppUrl::to('/verify/'.$verification->token);
    }

    public function verificationSvg(LaundryTransactionVerification $verification): string
    {
        return $this->svg($this->verificationUrl($verification));
    }

    /** Menghasilkan SVG QR Code (tanpa deklarasi XML agar aman di-embed). */
    public function svg(string $content): string
    {
        $renderer = new ImageRenderer(new RendererStyle(320), new SvgImageBackEnd());

        $svg = (new Writer($renderer))->writeString($content, 'UTF-8', $this->errorCorrectionLevel());

        return trim((string) preg_replace('/^<\?xml[^>]*\?>\s*/', '', $svg));
    }

    /**
     * Level koreksi kesalahan M (~15%). bacon-qr-code v3 memakai enum PHP native,
     * v2 memakai kelas dengan method statis. Keduanya didukung.
     */
    private function errorCorrectionLevel(): mixed
    {
        if (enum_exists(ErrorCorrectionLevel::class)) {
            return constant(ErrorCorrectionLevel::class.'::M');
        }

        return ErrorCorrectionLevel::M();
    }
}