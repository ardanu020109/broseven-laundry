<?php

namespace Database\Seeders;

use App\Models\LaundryWebsiteSetting;
use Illuminate\Database\Seeder;

class WebsiteSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // Identitas bisnis
            ['business', 'business_name', 'BroSeven! Laundry', 'text'],
            ['business', 'tagline', 'Cucian bersih, wangi, dan rapi tanpa ribet.', 'text'],
            ['business', 'description', 'BroSeven! Laundry adalah layanan laundry modern yang mengutamakan kebersihan, ketepatan waktu, dan transparansi proses. Setiap cucian dicatat, ditimbang, dan dapat dipantau statusnya kapan saja.', 'textarea'],
            ['business', 'hero_title', 'Laundry bersih dan wangi, tanpa repot.', 'text'],
            ['business', 'hero_subtitle', 'Serahkan cucian Anda kepada BroSeven! Laundry dan pantau setiap tahap prosesnya langsung dari ponsel.', 'textarea'],

            // Kontak (diisi admin lewat Pengaturan Website)
            ['contact', 'address', '', 'textarea'],
            ['contact', 'whatsapp', '', 'text'],
            ['contact', 'operating_hours', '', 'text'],
            ['contact', 'social_links', json_encode(['instagram' => '', 'facebook' => '', 'tiktok' => '', 'youtube' => '']), 'json'],

            // Branding (path file di storage publik)
            ['branding', 'logo_path', null, 'image'],
            ['branding', 'favicon_path', null, 'image'],

            // Visi dan misi
            ['about', 'vision', 'Menjadi layanan laundry pilihan yang dipercaya karena kebersihan, ketepatan waktu, dan transparansi proses.', 'textarea'],
            ['about', 'missions', json_encode([
                'Menjaga kualitas dan kebersihan setiap cucian pelanggan.',
                'Menyediakan proses laundry yang transparan melalui pelacakan status secara langsung.',
                'Mengerjakan cucian tepat waktu sesuai estimasi yang dijanjikan.',
                'Memberikan pelayanan yang ramah, jujur, dan profesional.',
            ], JSON_UNESCAPED_UNICODE), 'json'],
        ];

        foreach ($settings as [$group, $key, $value, $type]) {
            // firstOrCreate: perubahan admin tidak ditimpa saat seeding ulang.
            LaundryWebsiteSetting::firstOrCreate(
                ['key' => $key],
                ['group' => $group, 'value' => $value, 'type' => $type]
            );
        }
    }
}