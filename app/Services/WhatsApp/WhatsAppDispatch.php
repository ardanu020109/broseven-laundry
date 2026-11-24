<?php

namespace App\Services\WhatsApp;

/**
 * Hasil penyiapan/pengiriman pesan WhatsApp.
 *
 * delivered = true hanya boleh dipakai driver yang benar-benar mengirim
 * (mis. Business API). Click-to-Chat selalu false.
 */
final readonly class WhatsAppDispatch
{
    public function __construct(
        public string $mode,
        public ?string $url,
        public bool $delivered,
        public bool $requiresUserConfirmation,
        public string $notice,
    ) {
    }
}