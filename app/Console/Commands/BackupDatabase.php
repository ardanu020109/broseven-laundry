<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Throwable;

class BackupDatabase extends Command
{
    protected $signature = 'broseven:backup';

    protected $description = 'Membuat backup database MySQL/MariaDB (terkompresi) di storage/app/private/backups';

    public function handle(): int
    {
        $connection = (string) config('database.default');
        $config = (array) config("database.connections.{$connection}");

        if (! in_array($config['driver'] ?? '', ['mysql', 'mariadb'], true)) {
            $this->error('Backup hanya mendukung MySQL/MariaDB (koneksi saat ini: '.($config['driver'] ?? '-').').');

            return self::FAILURE;
        }

        // Backup memuat data customer dan hash password: simpan di folder privat, jangan di public/.
        $dir = storage_path('app/private/backups');
        File::ensureDirectoryExists($dir);

        $name = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $config['database']);
        $path = $dir.DIRECTORY_SEPARATOR."broseven_{$name}_".now()->format('Ymd_His').'.sql';

        $run = function (bool $noTablespaces) use ($config, $path): Process {
            $process = new Process(
                $this->arguments((string) config('broseven.backup.mysqldump', 'mysqldump'), $config, $path, $noTablespaces),
                null,
                ['MYSQL_PWD' => (string) ($config['password'] ?? '')] // password tidak tampil di daftar proses
            );
            $process->setTimeout(900);
            $process->run();

            return $process;
        };

        try {
            $process = $run(true);

            if (! $process->isSuccessful() && str_contains(strtolower($process->getErrorOutput()), 'unknown option')) {
                $process = $run(false);
            }
        } catch (Throwable $e) {
            $this->error('mysqldump tidak dapat dijalankan: '.$e->getMessage());
            $this->line('Atur MYSQLDUMP_PATH di .env ke lokasi mysqldump.');

            return self::FAILURE;
        }

        if (! $process->isSuccessful() || ! is_file($path) || filesize($path) === 0) {
            $this->error('mysqldump gagal: '.trim($process->getErrorOutput()));
            @unlink($path);

            return self::FAILURE;
        }

        if (function_exists('gzopen')) {
            $in = fopen($path, 'rb');
            $out = gzopen($path.'.gz', 'wb9');

            while (! feof($in)) {
                gzwrite($out, (string) fread($in, 1048576));
            }

            fclose($in);
            gzclose($out);
            unlink($path);
            $path .= '.gz';
        }

        $this->info('Backup tersimpan: '.$path.' ('.number_format(filesize($path) / 1024, 1).' KB)');
        $this->prune($dir);

        return self::SUCCESS;
    }

    private function prune(string $dir): void
    {
        $limit = now()->subDays(max(1, (int) config('broseven.retention.backup_days', 14)))->timestamp;

        foreach (File::files($dir) as $file) {
            if (preg_match('/^broseven_.*\.sql(\.gz)?$/', $file->getFilename()) && $file->getMTime() < $limit) {
                File::delete($file->getPathname());
            }
        }
    }
}
