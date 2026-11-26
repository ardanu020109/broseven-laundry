<?php

namespace Tests\Feature\Ops;

use App\Models\LaundryWebsiteSetting;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OperationsCommandsTest extends TestCase
{
    use RefreshDatabase;

    private function oldFile(string $disk, string $path): void
    {
        Storage::disk($disk)->put($path, 'x');
        touch(Storage::disk($disk)->path($path), time() - 3 * 86400);
    }

    public function test_old_activity_logs_are_pruned_and_dry_run_changes_nothing(): void
    {
        config(['broseven.retention.activity_log_days' => 30]);

        $user = User::factory()->create();
        $service = app(ActivityLogService::class);
        $old = $service->log('auth.login', 'lama', null, [], $user);
        $service->log('auth.login', 'baru', null, [], $user);

        DB::table('laundry_activity_logs')->where('id', $old->id)->update(['created_at' => now()->subDays(40)]);

        $this->artisan('broseven:prune', ['--dry-run' => true])->assertExitCode(0);
        $this->assertDatabaseCount('laundry_activity_logs', 2);

        $this->artisan('broseven:prune')->assertExitCode(0);
        $this->assertDatabaseCount('laundry_activity_logs', 1);
        $this->assertDatabaseHas('laundry_activity_logs', ['description' => 'baru']);
    }

    public function test_only_old_unreferenced_files_are_removed(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $usedAvatar = 'avatars/'.str_repeat('a', 40).'.png';
        $orphanAvatar = 'avatars/'.str_repeat('b', 40).'.png';
        $freshOrphan = 'avatars/'.str_repeat('c', 40).'.png';
        $usedLogo = 'branding/'.str_repeat('d', 40).'.png';
        $orphanLogo = 'branding/'.str_repeat('e', 40).'.png';

        User::factory()->create(['avatar_path' => $usedAvatar]);
        LaundryWebsiteSetting::create(['group' => 'branding', 'key' => 'logo_path', 'value' => $usedLogo, 'type' => 'image']);

        $this->oldFile('local', $usedAvatar);
        $this->oldFile('local', $orphanAvatar);
        Storage::disk('local')->put($freshOrphan, 'x'); // baru: masih masa tenggang
        $this->oldFile('public', $usedLogo);
        $this->oldFile('public', $orphanLogo);

        $this->artisan('broseven:prune')->assertExitCode(0);

        Storage::disk('local')->assertExists($usedAvatar);
        Storage::disk('local')->assertMissing($orphanAvatar);
        Storage::disk('local')->assertExists($freshOrphan);
        Storage::disk('public')->assertExists($usedLogo);
        Storage::disk('public')->assertMissing($orphanLogo);
    }

    public function test_production_check_passes_locally_and_fails_for_unsafe_production_settings(): void
    {
        $this->artisan('broseven:check')->assertExitCode(0);

        $this->app['env'] = 'production';
        config(['app.debug' => true]);

        $this->artisan('broseven:check')->assertExitCode(1);
    }

    public function test_backup_refuses_unsupported_databases(): void
    {
        $this->artisan('broseven:backup')->assertExitCode(1);
    }

    public function test_scheduler_lists_the_operational_tasks(): void
    {
        Artisan::call('schedule:list');
        $output = Artisan::output();

        foreach (['broseven:backup', 'broseven:prune', 'sanctum:prune-expired'] as $command) {
            $this->assertStringContainsString($command, $output);
        }
    }
}