<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Throwable;

class CheckProduction extends Command
{
    protected $signature = 'broseven:check';

    protected $description = 'Memeriksa kesiapan aplikasi untuk lingkungan production';

    /** @var array<int, array{0: string, 1: string, 2: string}> */
    private array $rows = [];

    public function handle(): int
    {
        $prod = app()->environment('production');
        $level = fn (bool $ok): string => $ok ? 'OK' : ($prod ? 'FAIL' : 'WARN');

        $this->add($prod ? 'OK' : 'WARN', 'APP_ENV', $prod ? 'production' : 'Bukan production ('.app()->environment().')');

        $debug = (bool) config('app.debug');
        $this->add($level(! $debug), 'APP_DEBUG', $debug ? 'Aktif. Wajib false di production (membocorkan detail error).' : 'Nonaktif');

        $this->add(filled(config('app.key')) ? 'OK' : 'FAIL', 'APP_KEY', filled(config('app.key')) ? 'Terisi' : 'Kosong. Jalankan php artisan key:generate');

        $url = (string) config('app.url');
        $https = str_starts_with($url, 'https://');
        $this->add($level($https), 'APP_URL', $https ? $url : "Bukan HTTPS ({$url}). Pemindai QR (kamera) dan cookie aman membutuhkan HTTPS.");

        $secure = (bool) config('session.secure');
        $this->add($level($secure), 'SESSION_SECURE_COOKIE', $secure ? 'true' : 'Belum true. Cookie sesi harus Secure di HTTPS.');

        $this->checkDatabase($prod, $level);
        $this->checkSanctum($prod, $level, $url);

        $writable = is_writable(storage_path('app')) && is_writable(base_path('bootstrap/cache'));
        $this->add($writable ? 'OK' : 'FAIL', 'Izin tulis', $writable ? 'storage/ dan bootstrap/cache dapat ditulis' : 'storage/ atau bootstrap/cache tidak dapat ditulis');

        if ($prod) {
            $built = is_file(public_path('build/manifest.json'));
            $this->add($built ? 'OK' : 'FAIL', 'Build frontend', $built ? 'public/build tersedia' : 'Belum ada. Jalankan npm run build.');
            $this->add(app()->configurationIsCached() ? 'OK' : 'WARN', 'Cache konfigurasi', app()->configurationIsCached() ? 'Aktif' : 'Belum. Jalankan php artisan optimize.');
        }

        $csp = (string) config('broseven.security.csp');
        $this->add($csp === 'enforce' ? 'OK' : 'WARN', 'CSP_MODE', $csp === 'enforce' ? 'enforce' : "{$csp}. Ubah ke enforce setelah memastikan tidak ada peringatan CSP di Console browser.");

        $this->table(
            ['Status', 'Pemeriksaan', 'Keterangan'],
            array_map(fn (array $r) => [
                match ($r[0]) {
                    'OK' => '<fg=green>OK</>',
                    'WARN' => '<fg=yellow>PERINGATAN</>',
                    default => '<fg=red>GAGAL</>',
                },
                $r[1],
                $r[2],
            ], $this->rows)
        );

        $failed = collect($this->rows)->where(0, 'FAIL')->count();

        $failed > 0
            ? $this->error("{$failed} pemeriksaan gagal. Perbaiki sebelum tayang.")
            : $this->info('Tidak ada pemeriksaan yang gagal.');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function checkDatabase(bool $prod, callable $level): void
    {
        try {
            DB::connection()->getPdo();
            $this->add('OK', 'Database', 'Terhubung ('.config('database.default').')');
        } catch (Throwable $e) {
            $this->add('FAIL', 'Database', 'Gagal terhubung: '.$e->getMessage());

            return;
        }

        try {
            $migrator = app('migrator');
            $files = $migrator->getMigrationFiles([database_path('migrations')]);
            $pending = count(array_diff(array_keys($files), $migrator->getRepository()->getRan()));

            $this->add($pending === 0 ? 'OK' : 'FAIL', 'Migrasi', $pending === 0 ? 'Semua migrasi sudah dijalankan' : "{$pending} migrasi belum dijalankan. Jalankan php artisan migrate --force.");
        } catch (Throwable) {
            $this->add('WARN', 'Migrasi', 'Status migrasi tidak dapat diperiksa.');
        }

        try {
            $weak = ['BroSeven@2026', 'Karyawan@2026', 'password', 'Sandi12345'];
            $bad = [];

            foreach (User::withTrashed()->limit(100)->get() as $user) {
                foreach ($weak as $password) {
                    if (Hash::check($password, $user->password)) {
                        $bad[] = $user->email;
                        break;
                    }
                }
            }

            $this->add($bad === [] ? 'OK' : $level(false), 'Password akun', $bad === [] ? 'Tidak ada akun dengan password bawaan' : 'Password bawaan masih dipakai: '.implode(', ', $bad));

            $demo = User::query()->where('email', 'karyawan@broseven.test')->exists();
            $this->add($demo ? $level(false) : 'OK', 'Akun demo', $demo ? 'karyawan@broseven.test masih ada. Nonaktifkan atau hapus di production.' : 'Tidak ada akun demo');

            $leftover = filled(config('broseven.admin.password'));
            $this->add($leftover && $prod ? 'WARN' : 'OK', 'ADMIN_PASSWORD', $leftover ? 'Masih ada di .env. Hapus setelah login pertama dan ganti password lewat Pengaturan Akun.' : 'Tidak ada di .env');
        } catch (Throwable) {
            $this->add('WARN', 'Akun', 'Akun tidak dapat diperiksa (tabel belum ada?).');
        }
    }

    private function checkSanctum(bool $prod, callable $level, string $url): void
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        $port = parse_url($url, PHP_URL_PORT);
        $stateful = (array) config('sanctum.stateful', []);
        $ok = $host !== '' && (in_array($host, $stateful, true) || ($port && in_array("{$host}:{$port}", $stateful, true)));

        $this->add($level($ok), 'SANCTUM_STATEFUL_DOMAINS', $ok ? "Memuat {$host}" : "Tidak memuat host APP_URL ({$host}). Login akan gagal.");
    }

    private function add(string $status, string $label, string $message): void
    {
        $this->rows[] = [$status, $label, $message];
    }
}