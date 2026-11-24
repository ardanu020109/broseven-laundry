<?php

namespace App\Support;

/**
 * Ikon prosedur yang didukung website publik (nama ikon Lucide).
 */
final class ProcedureIcons
{
    public const LABELS = [
        'shirt' => 'Baju',
        'scale' => 'Timbangan',
        'clipboard-list' => 'Catatan',
        'droplets' => 'Air / Cuci',
        'refresh-cw' => 'Pembaruan',
        'message-circle' => 'Pesan',
        'qr-code' => 'QR Code',
        'sparkles' => 'Bersih dan Wangi',
        'truck' => 'Antar Jemput',
        'package-check' => 'Paket Siap',
        'clock' => 'Waktu',
        'wind' => 'Pengeringan',
        'check-circle' => 'Selesai',
        'shield-check' => 'Jaminan',
    ];

    /** @return array<int, string> */
    public static function keys(): array
    {
        return array_keys(self::LABELS);
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        $options = [];

        foreach (self::LABELS as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }

        return $options;
    }
}