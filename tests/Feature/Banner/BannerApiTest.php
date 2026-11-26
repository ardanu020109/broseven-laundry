<?php

namespace Tests\Feature\Banner;

use App\Models\User;
use App\Support\BannerPresets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BannerApiTest extends TestCase
{
    use RefreshDatabase;

    private const PRESETS = '/api/admin/website/banner/presets';

    private const EXPORTS = '/api/admin/website/banner/exports';

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'preset' => 'spanduk-300x100',
            'width_cm' => 300,
            'height_cm' => 100,
            'quality' => 'standar',
            'package_count' => 4,
        ], $overrides);
    }

    public function test_guests_and_employees_are_rejected(): void
    {
        $this->getJson(self::PRESETS)->assertUnauthorized();
        $this->postJson(self::EXPORTS, $this->payload())->assertUnauthorized();

        $this->actingAs(User::factory()->create());

        $this->getJson(self::PRESETS)->assertForbidden();
        $this->postJson(self::EXPORTS, $this->payload())->assertForbidden();
    }

    public function test_admin_receives_all_requested_sizes_qualities_and_limits(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())->getJson(self::PRESETS)
            ->assertOk()
            ->assertJsonCount(2, 'data.groups')
            ->assertJsonCount(3, 'data.qualities')
            ->assertJsonPath('data.limits.max_dpi', 300);

        $presets = collect($response->json('data.groups'))->flatMap(fn ($g) => $g['presets'])->keyBy('key');

        foreach ([
            'spanduk-100x50' => [100, 50], 'spanduk-200x80' => [200, 80], 'spanduk-200x100' => [200, 100],
            'spanduk-300x100' => [300, 100], 'spanduk-400x100' => [400, 100], 'spanduk-300x200' => [300, 200],
            'mini-25x40' => [25, 40], 'mini-26x38' => [26, 38], 'standing-60x160' => [60, 160],
            'rollup-85x200' => [85, 200], 'tripod-60x80' => [60, 80], 'tripod-60x90' => [60, 90],
        ] as $key => [$w, $h]) {
            $this->assertSame($w, $presets[$key]['width_cm'], $key);
            $this->assertSame($h, $presets[$key]['height_cm'], $key);
        }
    }

    public function test_every_preset_is_unique_and_within_limits(): void
    {
        $keys = BannerPresets::keys();
        $this->assertSame($keys, array_values(array_unique($keys)));

        foreach (collect(BannerPresets::groups())->flatMap(fn ($g) => $g['presets']) as $preset) {
            foreach (['width_cm', 'height_cm'] as $side) {
                $this->assertGreaterThanOrEqual(BannerPresets::MIN_CM, $preset[$side]);
                $this->assertLessThanOrEqual(BannerPresets::MAX_CM, $preset[$side]);
            }
        }
    }

    public function test_exports_are_validated_and_logged(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $this->postJson(self::EXPORTS, $this->payload())->assertOk();

        $this->assertDatabaseHas('laundry_activity_logs', ['action' => 'website.banner_exported', 'user_id' => $admin->id]);

        $this->postJson(self::EXPORTS, $this->payload(['preset' => 'custom', 'width_cm' => 120.5, 'height_cm' => 80]))->assertOk();

        foreach ([
            ['preset' => 'tidak-ada'], ['width_cm' => 5], ['height_cm' => 601], ['quality' => 'ultra'], ['package_count' => 0],
        ] as $override) {
            $this->postJson(self::EXPORTS, $this->payload($override))->assertUnprocessable();
        }
    }
}