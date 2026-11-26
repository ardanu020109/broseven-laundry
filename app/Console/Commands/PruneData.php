<?php

namespace App\Console\Commands;

use App\Models\LaundryActivityLog;
use App\Models\LaundryWebsiteSetting;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneData extends Command
{
    protected $signature = 'broseven:prune {--dry-run : Hanya menampilkan apa yang akan dihapus}';

    protected $description = 'Menghapus log aktivitas lama serta foto/logo yang tidak dipakai lagi';

    /** File yatim baru dihapus setelah melewati masa tenggang (unggahan yang sedang berjalan aman). */
    private const GRACE_SECONDS = 86400;

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $days = max(30, (int) config('broseven.retention.activity_log_days', 365));

        $logs = LaundryActivityLog::query()->where('created_at', '<', now()->subDays($days));
        $count = $logs->count();

        if (! $dry) {
            $logs->delete();
        }

        $this->line(($dry ? '[uji coba] ' : '')."Log aktivitas lebih dari {$days} hari: {$count}");

        $avatars = $this->orphans(
            'local',
            'avatars',
            User::AVATAR_PATTERN,
            User::withTrashed()->whereNotNull('avatar_path')->pluck('avatar_path')->all(),
            $dry
        );

        $branding = $this->orphans(
            'public',
            'branding',
            '~^branding/[A-Za-z0-9]{40}\.(?:png|jpg|jpeg|webp|ico)$~',
            LaundryWebsiteSetting::query()->whereIn('key', ['logo_path', 'favicon_path'])->pluck('value')->filter()->all(),
            $dry
        );

        $this->line(($dry ? '[uji coba] ' : '')."Foto profil yatim: {$avatars}, file logo/favicon yatim: {$branding}");

        return self::SUCCESS;
    }

    /** @param  array<int, string>  $used */
    private function orphans(string $diskName, string $directory, string $pattern, array $used, bool $dry): int
    {
        $disk = Storage::disk($diskName);
        $count = 0;

        foreach ($disk->files($directory) as $file) {
            if (! preg_match($pattern, $file) || in_array($file, $used, true)) {
                continue;
            }

            if (now()->timestamp - $disk->lastModified($file) < self::GRACE_SECONDS) {
                continue;
            }

            $count++;

            if (! $dry) {
                $disk->delete($file);
            }
        }

        return $count;
    }
}