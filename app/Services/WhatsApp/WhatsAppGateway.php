<?php

namespace App\Services\WhatsApp;

/**
 * Kontrak pengiriman WhatsApp. Implementasi saat ini: ClickToChatGateway.
 *
 * Untuk WhatsApp Business API, buat kelas baru yang mengimplementasikan
 * interface ini (deliver() benar-benar mengirim dan mengembalikan
 * delivered: true), lalu daftarkan di AppServiceProvider.
 */
interface WhatsAppGateway
{
    /**
     * @param  string  $phone    Nomor format internasional, hanya angka (mis. 6281234567890)
     * @param  string  $message  Isi pesan polos
     */
    public function deliver(string $phone, string $message): WhatsAppDispatch;
}