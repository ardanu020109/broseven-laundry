<?php

namespace App\Services;

use App\Enums\PackageUnit;
use App\Enums\TransactionStatus;
use App\Models\LaundryTransaction;
use App\Models\LaundryWebsiteSetting;
use App\Models\User;
use App\Services\WhatsApp\WhatsAppGateway;
use App\Support\AppUrl;
use App\Support\PhoneNumber;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class WhatsAppService
{
    public function __construct(
        private readonly WhatsAppGateway $gateway,
        private readonly ActivityLogService $activity,
    ) {
    }

    /**
     * Daftar jenis pesan (satu per status).
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function templates(): array
    {
        return array_map(
            fn (TransactionStatus $status) => [
                'value' => $status->value,
                'label' => $this->templateLabel($status),
            ],
            TransactionStatus::cases()
        );
    }

    /**
     * Menyiapkan pesan dan link WhatsApp. Tidak mengirim apa pun dan tidak
     * menyimpan apa pun. Jenis pesan default mengikuti status transaksi saat ini.
     *
     * @return array<string, mixed>
     */
    public function prepare(LaundryTransaction $transaction, ?string $template = null): array
    {
        $phone = PhoneNumber::normalize($transaction->customer_phone);

        if (! PhoneNumber::isValid($phone)) {
            throw ValidationException::withMessages([
                'customer_phone' => 'Nomor WhatsApp customer tidak valid. Perbarui nomor pada transaksi terlebih dahulu.',
            ]);
        }

        $type = $template !== null && $template !== ''
            ? TransactionStatus::from($template)
            : $transaction->status;

        $message = $this->buildMessage($transaction, $type);
        $dispatch = $this->gateway->deliver($phone, $message);

        return [
            'transaction_id' => $transaction->id,
            'code' => $transaction->code,
            'phone' => $phone,
            'template' => $type->value,
            'template_label' => $this->templateLabel($type),
            'recommended_template' => $transaction->status->value,
            'templates' => $this->templates(),
            'message' => $message,
            'whatsapp_url' => $dispatch->url,
            'mode' => $dispatch->mode,
            'delivered' => $dispatch->delivered,
            'requires_user_confirmation' => $dispatch->requiresUserConfirmation,
            'notice' => $dispatch->notice,
        ];
    }

    /**
     * Dipanggil saat petugas menekan "Buka WhatsApp". Hanya mencatat bahwa
     * WhatsApp dibuka, bukan bahwa pesan terkirim.
     *
     * @return array<string, mixed>
     */
    public function recordOpened(LaundryTransaction $transaction, User $actor, ?string $template = null): array
    {
        $data = $this->prepare($transaction, $template);

        $this->activity->log(
            'whatsapp.opened',
            "Membuka WhatsApp untuk transaksi {$transaction->code} (pesan: {$data['template_label']})",
            $transaction,
            [
                'template' => $data['template'],
                'mode' => $data['mode'],
                'phone_suffix' => substr($data['phone'], -4),
            ],
            $actor
        );

        return $data;
    }

    public function buildMessage(LaundryTransaction $transaction, TransactionStatus $type): string
    {
        $profile = $this->profile();
        $trackingUrl = AppUrl::to('/tracking/'.$transaction->tracking_token);

        $lines = match ($type) {
            TransactionStatus::Received => $this->receivedLines($transaction, $profile, $trackingUrl),
            TransactionStatus::Washing,
            TransactionStatus::Drying,
            TransactionStatus::Ironing => $this->progressLines($transaction, $type, $profile, $trackingUrl),
            TransactionStatus::Ready => $this->readyLines($transaction, $profile, $trackingUrl),
            TransactionStatus::Completed => $this->completedLines($transaction, $profile),
            TransactionStatus::Cancelled => $this->cancelledLines($transaction, $profile),
        };

        return $this->compose($lines);
    }

    /* ---------------------------------------------------------------
     | Template pesan
     | ------------------------------------------------------------- */

    /**
     * @param  array{business: string, address: ?string, hours: ?string}  $profile
     * @return array<int, ?string>
     */
    private function receivedLines(LaundryTransaction $t, array $profile, string $url): array
    {
        return [
            "Halo Kak {$t->customer_name}!",
            '',
            "Terima kasih telah menggunakan layanan {$profile['business']}.",
            '',
            'Detail transaksi:',
            "Kode: {$t->code}",
            "Paket: {$t->package_name}",
            $this->quantityLabel($t).': '.$this->quantity($t),
            'Total: '.$this->rupiah($t->total_price),
            'Estimasi selesai: '.$this->dateTime($t->estimated_completion_at),
            '',
            "Status cucian: {$t->status->label()}",
            '',
            'Pantau cucian Kakak melalui:',
            $url,
            '',
            'Simpan QR Code transaksi di halaman tersebut sebagai bukti pengambilan cucian.',
            '',
            'Terima kasih!',
        ];
    }

    /**
     * @param  array{business: string, address: ?string, hours: ?string}  $profile
     * @return array<int, ?string>
     */
    private function progressLines(LaundryTransaction $t, TransactionStatus $type, array $profile, string $url): array
    {
        $sentence = match ($type) {
            TransactionStatus::Washing => 'Cucian Kakak sedang kami cuci.',
            TransactionStatus::Drying => 'Cucian Kakak sedang kami keringkan.',
            default => 'Cucian Kakak sedang kami setrika.',
        };

        return [
            "Halo Kak {$t->customer_name}!",
            '',
            $sentence,
            '',
            "Kode: {$t->code}",
            "Status: {$type->label()}",
            'Estimasi selesai: '.$this->dateTime($t->estimated_completion_at),
            '',
            'Pantau perkembangan cucian Kakak melalui:',
            $url,
            '',
            "Terima kasih telah mempercayakan cucian Kakak kepada {$profile['business']}.",
        ];
    }

    /**
     * @param  array{business: string, address: ?string, hours: ?string}  $profile
     * @return array<int, ?string>
     */
    private function readyLines(LaundryTransaction $t, array $profile, string $url): array
    {
        $hasPlace = $profile['address'] !== null || $profile['hours'] !== null;

        return [
            "Halo Kak {$t->customer_name}!",
            '',
            'Kabar baik! Cucian Kakak sudah selesai dan siap diambil.',
            '',
            "Kode: {$t->code}",
            "Paket: {$t->package_name}",
            $this->quantityLabel($t).': '.$this->quantity($t),
            'Total: '.$this->rupiah($t->total_price),
            '',
            'Saat pengambilan, mohon tunjukkan QR Code bukti transaksi kepada petugas. QR Code tersedia di halaman berikut:',
            $url,
            $hasPlace ? '' : null,
            $profile['address'] !== null ? "Alamat pengambilan: {$profile['address']}" : null,
            $profile['hours'] !== null ? "Jam operasional: {$profile['hours']}" : null,
            '',
            "Terima kasih telah menggunakan layanan {$profile['business']}!",
        ];
    }

    /**
     * @param  array{business: string, address: ?string, hours: ?string}  $profile
     * @return array<int, ?string>
     */
    private function completedLines(LaundryTransaction $t, array $profile): array
    {
        return [
            "Halo Kak {$t->customer_name}!",
            '',
            "Cucian Kakak dengan kode {$t->code} sudah diambil. Terima kasih telah mempercayakan cucian Kakak kepada {$profile['business']}.",
            '',
            'Sampai jumpa di laundry berikutnya!',
        ];
    }

    /**
     * @param  array{business: string, address: ?string, hours: ?string}  $profile
     * @return array<int, ?string>
     */
    private function cancelledLines(LaundryTransaction $t, array $profile): array
    {
        return [
            "Halo Kak {$t->customer_name},",
            '',
            "Transaksi {$t->code} di {$profile['business']} telah dibatalkan.",
            '',
            'Jika ada pertanyaan, silakan balas pesan ini dan kami siap membantu.',
            '',
            'Terima kasih.',
        ];
    }

    /* ---------------------------------------------------------------
     | Pembantu
     | ------------------------------------------------------------- */

    private function templateLabel(TransactionStatus $status): string
    {
        return match ($status) {
            TransactionStatus::Received => 'Transaksi diterima (detail lengkap)',
            TransactionStatus::Washing => 'Sedang dicuci',
            TransactionStatus::Drying => 'Sedang dikeringkan',
            TransactionStatus::Ironing => 'Sedang disetrika',
            TransactionStatus::Ready => 'Siap diambil',
            TransactionStatus::Completed => 'Sudah diambil (terima kasih)',
            TransactionStatus::Cancelled => 'Transaksi dibatalkan',
        };
    }

    /**
     * Baris null dibuang (opsional); baris kosong dipertahankan sebagai pemisah paragraf.
     *
     * @param  array<int, ?string>  $lines
     */
    private function compose(array $lines): string
    {
        $text = implode("\n", array_filter($lines, fn (?string $line) => $line !== null));

        return trim((string) preg_replace("/\n{3,}/", "\n\n", $text));
    }

    /**
     * Profil bisnis dari pengaturan website (dikelola admin di Fase 10).
     * Alamat dan jam operasional dilewati jika belum diisi.
     *
     * @return array{business: string, address: ?string, hours: ?string}
     */
    private function profile(): array
    {
        $settings = LaundryWebsiteSetting::query()
            ->whereIn('key', ['business_name', 'address', 'operating_hours'])
            ->pluck('value', 'key');

        $clean = function (string $key) use ($settings): ?string {
            $value = trim((string) preg_replace('/\s+/u', ' ', (string) $settings->get($key)));

            return $value === '' ? null : $value;
        };

        return [
            'business' => $clean('business_name') ?? (string) config('app.name'),
            'address' => $clean('address'),
            'hours' => $clean('operating_hours'),
        ];
    }

    private function quantityLabel(LaundryTransaction $t): string
    {
        return $t->package_unit === PackageUnit::Kg ? 'Berat' : 'Jumlah';
    }

    private function quantity(LaundryTransaction $t): string
    {
        $unit = $t->package_unit === PackageUnit::Kg ? 'kg' : 'item';

        return $this->number((string) $t->quantity)." {$unit}";
    }

    /** 2.50 => "2,5"; 3.00 => "3". Hanya untuk tampilan. */
    private function number(string $value): string
    {
        $formatted = number_format((float) $value, 2, ',', '.');

        return rtrim(rtrim($formatted, '0'), ',');
    }

    /** 20000.00 => "Rp20.000"; 2566.66 => "Rp2.566,66". Hanya untuk tampilan. */
    private function rupiah(string|float|null $value): string
    {
        $amount = bcadd((string) ($value ?? 0), '0', 2);
        $isWhole = str_ends_with($amount, '.00');

        return 'Rp'.number_format((float) $amount, $isWhole ? 0 : 2, ',', '.');
    }

    private function dateTime(?Carbon $date): string
    {
        if ($date === null) {
            return 'akan kami informasikan';
        }

        $timezone = (string) config('app.timezone');

        return $date->copy()->timezone($timezone)->translatedFormat('d M Y, H:i').' '.$this->zoneLabel($timezone);
    }

    private function zoneLabel(string $timezone): string
    {
        return match ($timezone) {
            'Asia/Jakarta', 'Asia/Pontianak' => 'WIB',
            'Asia/Makassar' => 'WITA',
            'Asia/Jayapura' => 'WIT',
            default => $timezone,
        };
    }
}