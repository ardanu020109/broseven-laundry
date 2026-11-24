<?php

namespace Tests\Feature\Website;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WebsiteSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    private const SETTINGS = '/api/admin/website/settings';

    private const COLORS = '/api/admin/website/colors';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->admin = User::factory()->admin()->create();
    }

    private function upload(string $kind, UploadedFile $file)
    {
        return $this->actingAs($this->admin)
            ->post("/api/admin/website/images/{$kind}", ['image' => $file], ['Accept' => 'application/json']);
    }

    /* ------------------------------ Akses ------------------------------ */

    public function test_guests_are_rejected(): void
    {
        $this->getJson('/api/admin/website')->assertUnauthorized();
        $this->putJson(self::SETTINGS)->assertUnauthorized();
        $this->putJson(self::COLORS)->assertUnauthorized();
        $this->postJson('/api/admin/website/colors/reset')->assertUnauthorized();
        $this->deleteJson('/api/admin/website/images/logo')->assertUnauthorized();
        $this->post('/api/admin/website/images/logo', [], ['Accept' => 'application/json'])->assertUnauthorized();
    }

    public function test_employees_are_rejected(): void
    {
        $this->actingAs(User::factory()->create());

        $this->getJson('/api/admin/website')->assertForbidden();
        $this->putJson(self::SETTINGS)->assertForbidden();
        $this->putJson(self::COLORS)->assertForbidden();
        $this->postJson('/api/admin/website/colors/reset')->assertForbidden();
        $this->deleteJson('/api/admin/website/images/logo')->assertForbidden();
        $this->post('/api/admin/website/images/logo', [], ['Accept' => 'application/json'])->assertForbidden();
    }

    /* ------------------------------ Tampilan ------------------------------ */

    public function test_admin_sees_settings_and_the_five_colors(): void
    {
        $this->actingAs($this->admin)->getJson('/api/admin/website')
            ->assertOk()
            ->assertJsonPath('data.settings.business_name', config('app.name'))
            ->assertJsonPath('data.settings.logo_url', null)
            ->assertJsonPath('data.settings.favicon_url', null)
            ->assertJsonCount(5, 'data.colors')
            ->assertJsonPath('data.colors.0.key', 'primary')
            ->assertJsonPath('data.colors.0.value', '#ADD8E6')
            ->assertJsonPath('data.colors.0.default_value', '#ADD8E6')
            ->assertJsonPath('data.colors.0.is_default', true);
    }

    /* ------------------------------ Pengaturan teks ------------------------------ */

    public function test_admin_updates_settings_with_sanitising_and_normalisation(): void
    {
        $response = $this->actingAs($this->admin)->putJson(self::SETTINGS, [
            'business_name' => '  <b>Laundry</b>   Uji  ',
            'tagline' => 'Bersih dan wangi',
            'description' => "Baris satu\n\n<script>alert(1)</script>Baris dua",
            'whatsapp' => '0812-3456-7890',
            'address' => "Jl. Contoh No. 7\nMakassar",
            'operating_hours' => '08.00 - 20.00 WITA',
            'social_links' => ['instagram' => 'https://instagram.com/laundryuji', 'facebook' => ''],
            'vision' => 'Menjadi yang terbaik',
            'missions' => ['  Misi satu  ', '', 'Misi dua'],
        ])->assertOk()
            ->assertJsonPath('message', 'Pengaturan website berhasil disimpan.')
            ->assertJsonPath('data.business_name', 'Laundry Uji')
            ->assertJsonPath('data.whatsapp', '6281234567890')
            ->assertJsonPath('data.address', "Jl. Contoh No. 7\nMakassar")
            ->assertJsonPath('data.social_links.instagram', 'https://instagram.com/laundryuji')
            ->assertJsonPath('data.social_links.facebook', '')
            ->assertJsonPath('data.missions', ['Misi satu', 'Misi dua']);

        $this->assertStringNotContainsString('<script', (string) $response->json('data.description'));

        $this->assertDatabaseHas('laundry_website_settings', ['key' => 'whatsapp', 'value' => '6281234567890']);
        $this->assertDatabaseHas('laundry_activity_logs', ['action' => 'website.updated', 'user_id' => $this->admin->id]);
    }

    public function test_partial_updates_keep_other_values_and_unchanged_values_are_not_logged_again(): void
    {
        $this->actingAs($this->admin);

        $this->putJson(self::SETTINGS, ['business_name' => 'Laundry Uji'])->assertOk();
        $this->putJson(self::SETTINGS, ['business_name' => 'Laundry Uji'])->assertOk();

        $this->assertDatabaseCount('laundry_activity_logs', 1);

        $this->putJson(self::SETTINGS, ['tagline' => 'Slogan baru'])
            ->assertOk()
            ->assertJsonPath('data.business_name', 'Laundry Uji')
            ->assertJsonPath('data.tagline', 'Slogan baru');
    }

    public function test_optional_fields_can_be_cleared(): void
    {
        $this->actingAs($this->admin);

        $this->putJson(self::SETTINGS, ['tagline' => 'Slogan', 'whatsapp' => '081234567890'])->assertOk();

        $this->putJson(self::SETTINGS, ['tagline' => '', 'whatsapp' => ''])
            ->assertOk()
            ->assertJsonPath('data.tagline', null)
            ->assertJsonPath('data.whatsapp', null);
    }

    public function test_settings_validation(): void
    {
        $this->actingAs($this->admin);

        $this->putJson(self::SETTINGS, ['business_name' => ''])
            ->assertUnprocessable()->assertJsonValidationErrors('business_name');

        $this->putJson(self::SETTINGS, ['business_name' => str_repeat('a', 101)])
            ->assertUnprocessable()->assertJsonValidationErrors('business_name');

        $this->putJson(self::SETTINGS, ['whatsapp' => '0812'])
            ->assertUnprocessable()->assertJsonValidationErrors('whatsapp');

        $this->putJson(self::SETTINGS, ['social_links' => ['instagram' => 'javascript:alert(1)']])
            ->assertUnprocessable()->assertJsonValidationErrors('social_links.instagram');

        $this->putJson(self::SETTINGS, ['social_links' => ['facebook' => 'bukan url']])
            ->assertUnprocessable()->assertJsonValidationErrors('social_links.facebook');

        $this->putJson(self::SETTINGS, ['missions' => array_map(fn ($i) => "Misi {$i}", range(1, 11))])
            ->assertUnprocessable()->assertJsonValidationErrors('missions');
    }

    public function test_image_paths_cannot_be_set_through_the_settings_endpoint(): void
    {
        $this->actingAs($this->admin)
            ->putJson(self::SETTINGS, ['business_name' => 'Uji', 'logo_path' => 'branding/'.str_repeat('a', 40).'.png'])
            ->assertOk()
            ->assertJsonPath('data.logo_url', null);

        $this->assertDatabaseMissing('laundry_website_settings', ['key' => 'logo_path']);
    }

    /* ------------------------------ Logo dan favicon ------------------------------ */

    public function test_logo_upload_is_stored_served_and_replaced(): void
    {
        $first = $this->upload('logo', UploadedFile::fake()->image('logo.png', 300, 300))
            ->assertOk()
            ->assertJsonPath('message', 'Logo berhasil diperbarui.');

        $url = $first->json('data.logo_url');
        $this->assertMatchesRegularExpression('~^/api/public/media/[A-Za-z0-9]{40}\.png$~', $url);

        $firstFile = basename($url);
        Storage::disk('public')->assertExists('branding/'.$firstFile);

        $served = $this->get($url)->assertOk();
        $this->assertSame('image/png', $served->headers->get('Content-Type'));
        $this->assertSame('nosniff', $served->headers->get('X-Content-Type-Options'));

        $second = $this->upload('logo', UploadedFile::fake()->image('baru.png', 300, 300))->assertOk();
        $secondFile = basename($second->json('data.logo_url'));

        $this->assertNotSame($firstFile, $secondFile);
        Storage::disk('public')->assertMissing('branding/'.$firstFile);
        Storage::disk('public')->assertExists('branding/'.$secondFile);

        $this->assertDatabaseHas('laundry_activity_logs', ['action' => 'website.image_updated']);
    }

    public function test_favicon_upload_and_removal(): void
    {
        $response = $this->upload('favicon', UploadedFile::fake()->image('favicon.png', 64, 64))
            ->assertOk()
            ->assertJsonPath('message', 'Favicon berhasil diperbarui.')
            ->assertJsonPath('data.logo_url', null);

        $file = basename($response->json('data.favicon_url'));
        Storage::disk('public')->assertExists('branding/'.$file);

        $this->actingAs($this->admin)->deleteJson('/api/admin/website/images/favicon')
            ->assertOk()
            ->assertJsonPath('data.favicon_url', null);

        Storage::disk('public')->assertMissing('branding/'.$file);

        // Menghapus lagi tidak menimbulkan error.
        $this->actingAs($this->admin)->deleteJson('/api/admin/website/images/favicon')->assertOk();
    }

    public function test_logo_can_be_removed(): void
    {
        $file = basename($this->upload('logo', UploadedFile::fake()->image('logo.png', 100, 100))->json('data.logo_url'));

        $this->actingAs($this->admin)->deleteJson('/api/admin/website/images/logo')
            ->assertOk()
            ->assertJsonPath('message', 'Logo dihapus.')
            ->assertJsonPath('data.logo_url', null);

        Storage::disk('public')->assertMissing('branding/'.$file);
    }

    public function test_unsafe_or_oversized_images_are_rejected_and_nothing_is_stored(): void
    {
        // SVG dapat memuat skrip: tidak diizinkan.
        $this->upload('logo', UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'))
            ->assertUnprocessable()->assertJsonValidationErrors('image');

        $this->upload('logo', UploadedFile::fake()->create('dokumen.pdf', 10, 'application/pdf'))
            ->assertUnprocessable()->assertJsonValidationErrors('image');

        $this->upload('logo', UploadedFile::fake()->image('besar.png')->size(3000))
            ->assertUnprocessable()->assertJsonValidationErrors('image');

        $this->upload('logo', UploadedFile::fake()->image('lebar.png', 5000, 100))
            ->assertUnprocessable()->assertJsonValidationErrors('image');

        $this->upload('favicon', UploadedFile::fake()->image('fav.png', 64, 64)->size(600))
            ->assertUnprocessable()->assertJsonValidationErrors('image');

        $this->actingAs($this->admin)->postJson('/api/admin/website/images/logo', [])
            ->assertUnprocessable()->assertJsonValidationErrors('image');

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_unknown_image_kinds_return_404(): void
    {
        $this->actingAs($this->admin)
            ->post('/api/admin/website/images/banner', [], ['Accept' => 'application/json'])
            ->assertNotFound();
    }

    /* ------------------------------ Warna ------------------------------ */

    public function test_admin_changes_colors_and_the_public_site_follows(): void
    {
        $response = $this->actingAs($this->admin)->putJson(self::COLORS, [
            'colors' => ['primary' => '#f4a6c0', 'evil' => '#000000'],
        ])->assertOk()->assertJsonPath('message', 'Warna website berhasil disimpan.');

        $items = collect($response->json('data'))->keyBy('key');

        $this->assertSame('#F4A6C0', $items['primary']['value']);
        $this->assertFalse($items['primary']['is_default']);
        $this->assertTrue($items['secondary']['is_default']);

        $this->assertDatabaseHas('laundry_colors', ['key' => 'primary', 'value' => '#F4A6C0', 'default_value' => '#ADD8E6']);
        $this->assertDatabaseMissing('laundry_colors', ['key' => 'evil']);
        $this->assertDatabaseHas('laundry_activity_logs', ['action' => 'website.colors_updated']);

        $this->getJson('/api/public/site')
            ->assertOk()
            ->assertJsonPath('data.colors.primary', '#F4A6C0')
            ->assertJsonPath('data.colors.secondary', '#ADBCE6');
    }

    public function test_invalid_hex_values_are_rejected(): void
    {
        $this->actingAs($this->admin);

        foreach (['blue', '#12345', '#GGGGGG', '123456'] as $bad) {
            $this->putJson(self::COLORS, ['colors' => ['primary' => $bad]])
                ->assertUnprocessable()->assertJsonValidationErrors('colors.primary');
        }

        $this->putJson(self::COLORS, ['colors' => []])->assertUnprocessable()->assertJsonValidationErrors('colors');
        $this->putJson(self::COLORS, [])->assertUnprocessable()->assertJsonValidationErrors('colors');

        $this->assertDatabaseCount('laundry_colors', 0);
    }

    public function test_unreadable_color_combinations_are_rejected(): void
    {
        $this->actingAs($this->admin);

        // Teks putih di atas latar hampir putih.
        $this->putJson(self::COLORS, ['colors' => ['text' => '#FFFFFF']])
            ->assertUnprocessable()->assertJsonValidationErrors('colors.text');

        // Latar sama dengan warna teks.
        $this->putJson(self::COLORS, ['colors' => ['background' => '#243746']])
            ->assertUnprocessable()->assertJsonValidationErrors('colors.background');

        // Warna utama terlalu gelap untuk tulisan pada tombol.
        $this->putJson(self::COLORS, ['colors' => ['primary' => '#000000']])
            ->assertUnprocessable()->assertJsonValidationErrors('colors.primary');

        $this->assertDatabaseCount('laundry_colors', 0);
    }

    public function test_colors_can_be_reset_fully_or_partially(): void
    {
        $this->actingAs($this->admin);

        $this->putJson(self::COLORS, ['colors' => ['primary' => '#F4A6C0', 'accent' => '#FFE08A']])->assertOk();

        $partial = $this->postJson('/api/admin/website/colors/reset', ['keys' => ['primary']])
            ->assertOk()
            ->assertJsonPath('message', 'Warna dikembalikan ke default.');

        $items = collect($partial->json('data'))->keyBy('key');
        $this->assertTrue($items['primary']['is_default']);
        $this->assertSame('#FFE08A', $items['accent']['value']);

        $full = $this->postJson('/api/admin/website/colors/reset')->assertOk();

        foreach ($full->json('data') as $item) {
            $this->assertTrue($item['is_default']);
            $this->assertSame($item['default_value'], $item['value']);
        }

        $this->assertDatabaseHas('laundry_activity_logs', ['action' => 'website.colors_reset']);

        $this->postJson('/api/admin/website/colors/reset', ['keys' => ['bogus']])
            ->assertUnprocessable()->assertJsonValidationErrors('keys.0');
    }
}