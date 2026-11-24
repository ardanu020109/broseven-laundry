<?php

namespace Database\Seeders;

use App\Models\LaundryProcedure;
use Illuminate\Database\Seeder;

class LaundryProcedureSeeder extends Seeder
{
    public function run(): void
    {
        // Hanya mengisi saat tabel kosong, agar prosedur yang dihapus admin tidak muncul lagi.
        if (LaundryProcedure::query()->exists()) {
            return;
        }

        $steps = [
            ['Serahkan Cucian', 'Customer menyerahkan cucian ke gerai kami.', 'shirt'],
            ['Penimbangan', 'Karyawan menimbang cucian dengan teliti di hadapan customer.', 'scale'],
            ['Pencatatan Transaksi', 'Karyawan mencatat transaksi dan memberikan bukti transaksi.', 'clipboard-list'],
            ['Proses Pengerjaan', 'Cucian diproses sesuai paket yang dipilih.', 'droplets'],
            ['Pembaruan Status', 'Status cucian diperbarui di setiap tahap dan dapat dipantau lewat link tracking.', 'refresh-cw'],
            ['Notifikasi WhatsApp', 'Customer menerima informasi status cucian melalui WhatsApp.', 'message-circle'],
            ['Pengambilan Cucian', 'Customer mengambil cucian dengan menunjukkan bukti transaksi berupa QR Code.', 'qr-code'],
        ];

        foreach ($steps as $index => [$title, $description, $icon]) {
            LaundryProcedure::create([
                'title' => $title,
                'description' => $description,
                'icon' => $icon,
                'sort_order' => $index + 1,
                'is_active' => true,
            ]);
        }
    }
}