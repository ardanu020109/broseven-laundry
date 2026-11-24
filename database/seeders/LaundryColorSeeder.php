<?php

namespace Database\Seeders;

use App\Models\LaundryColor;
use Illuminate\Database\Seeder;

class LaundryColorSeeder extends Seeder
{
    public function run(): void
    {
        $colors = [
            ['key' => 'primary', 'label' => 'Warna Utama (Light Blue)', 'value' => '#ADD8E6'],
            ['key' => 'secondary', 'label' => 'Warna Sekunder (Perano)', 'value' => '#ADBCE6'],
            ['key' => 'accent', 'label' => 'Warna Aksen (Ice Cold)', 'value' => '#ADE6D8'],
            ['key' => 'background', 'label' => 'Warna Latar', 'value' => '#F8FCFD'],
            ['key' => 'text', 'label' => 'Warna Teks', 'value' => '#243746'],
        ];

        foreach ($colors as $color) {
            // firstOrCreate: warna yang sudah diubah admin tidak ditimpa saat seeding ulang.
            LaundryColor::firstOrCreate(
                ['key' => $color['key']],
                [
                    'label' => $color['label'],
                    'value' => $color['value'],
                    'default_value' => $color['value'],
                ]
            );
        }
    }
}