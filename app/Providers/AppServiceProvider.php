<?php

namespace App\Providers;

use App\Models\User;
use App\Services\WhatsApp\ClickToChatGateway;
use App\Services\WhatsApp\WhatsAppGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerWhatsAppGateway();
    }

    public function boot(): void
    {
        $this->defineGates();
        $this->defineRateLimiters();
    }

    /**
     * Driver WhatsApp dipilih lewat WHATSAPP_DRIVER (config/broseven.php).
     * Untuk WhatsApp Business API: buat kelas yang mengimplementasikan
     * WhatsAppGateway, lalu tambahkan satu baris pada match di bawah.
     */
    private function registerWhatsAppGateway(): void
    {
        $this->app->bind(WhatsAppGateway::class, function () {
            $driver = (string) config('broseven.whatsapp.driver', 'click_to_chat');

            return match ($driver) {
                'click_to_chat' => new ClickToChatGateway(
                    (string) config('broseven.whatsapp.click_to_chat.base_url', 'https://wa.me')
                ),
                default => throw new InvalidArgumentException("Driver WhatsApp tidak dikenal: {$driver}"),
            };
        });
    }

    /**
     * Gate fitur. Pemakaian:
     *   route : ->middleware('can:view-reports')
     *   kode  : Gate::authorize('view-reports')
     * Akun nonaktif ditolak oleh semua Gate.
     */
    private function defineGates(): void
    {
        $staff = fn (User $user): bool => $user->is_active
            && ($user->isAdmin() || $user->isEmployee());

        $admin = fn (User $user): bool => $user->is_active && $user->isAdmin();

        Gate::define('manage-transactions', $staff); // admin + karyawan
        Gate::define('view-revenue', $admin);        // total pemasukan di dashboard
        Gate::define('view-reports', $admin);        // laporan pemasukan
        Gate::define('manage-website', $admin);      // pengaturan website
        Gate::define('manage-employees', $admin);    // kelola karyawan
    }

    /**
     * Pembatasan endpoint publik (per IP). Di balik proxy/hosting, pastikan
     * trusted proxies dikonfigurasi agar IP asli terbaca (Fase 14).
     */
    private function defineRateLimiters(): void
    {
        RateLimiter::for('tracking', fn (Request $request) => Limit::perMinute(40)->by('tracking|'.$request->ip()));

        RateLimiter::for('tracking-qr', fn (Request $request) => Limit::perMinute(60)->by('tracking-qr|'.$request->ip()));
    }
}