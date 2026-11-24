<?php

namespace App\Services\WhatsApp;

/**
 * WhatsApp Click-to-Chat: membuat link wa.me dengan teks terisi.
 * Tidak mengirim apa pun; pengguna harus menekan Kirim di WhatsApp.
 */
final class ClickToChatGateway implements WhatsAppGateway
{
    public function __construct(private readonly string $baseUrl = 'https://wa.me')
    {
    }

    public function deliver(string $phone, string $message): WhatsAppDispatch
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return new WhatsAppDispatch(
            mode: 'click_to_chat',
            url: rtrim($this->baseUrl, '/').'/'.$digits.'?text='.rawurlencode($message),
            delivered: false,
            requiresUserConfirmation: true,
            notice: 'Pengiriman lewat WhatsApp Click-to-Chat membutuhkan konfirmasi Anda. '
                .'Pesan baru terkirim setelah Anda menekan tombol Kirim di aplikasi WhatsApp.',
        );
    }
}