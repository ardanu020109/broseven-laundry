<?php

namespace Database\Seeders;

use App\Models\LaundryFaq;
use Illuminate\Database\Seeder;

class LaundryFaqSeeder extends Seeder
{
    public function run(): void
    {
        if (LaundryFaq::query()->exists()) {
            return;
        }

        $faqs = [
            [
                'Bagaimana cara memantau status cucian saya?',
                'Setelah cucian diterima, Anda akan mendapat link tracking unik melalui WhatsApp. Buka link tersebut kapan saja untuk melihat status terbaru tanpa perlu login.',
            ],
            [
                'Apa fungsi QR Code pada bukti transaksi?',
                'QR Code digunakan petugas untuk memverifikasi transaksi saat Anda mengambil cucian. Simpan QR Code tersebut dan tunjukkan kepada petugas ketika pengambilan.',
            ],
            [
                'Bagaimana harga laundry dihitung?',
                'Harga dihitung dari harga paket yang dipilih dikalikan berat (kg) atau jumlah item cucian. Harga paket yang berlaku saat transaksi dicatat tidak berubah untuk transaksi tersebut.',
            ],
            [
                'Kapan cucian saya bisa diambil?',
                'Cucian dapat diambil setelah statusnya Siap Diambil. Estimasi waktu selesai tertera pada detail transaksi dan halaman tracking.',
            ],
            [
                'Bagaimana jika saya kehilangan bukti transaksi?',
                'Hubungi kami melalui WhatsApp dengan menyebutkan nama dan nomor WhatsApp yang terdaftar. Petugas akan membantu memverifikasi transaksi Anda.',
            ],
        ];

        foreach ($faqs as $index => [$question, $answer]) {
            LaundryFaq::create([
                'question' => $question,
                'answer' => $answer,
                'sort_order' => $index + 1,
                'is_active' => true,
            ]);
        }
    }
}