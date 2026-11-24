<?php

namespace Tests\Feature\Website;

use App\Models\LaundryColor;
use App\Models\LaundryFaq;
use App\Models\LaundryPackage;
use App\Models\LaundryProcedure;
use App\Models\LaundryWebsiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicSiteApiTest extends TestCase
{
    use RefreshDatabase;

    private function setting(string $key, string $value, string $group = 'business', string $type = 'text'): void
    {
        LaundryWebsiteSetting::create(compact('group', 'key', 'value', 'type'));
    }

    public function test_site_is_public_and_has_the_expected_structure_with_defaults(): void
    {
        $this->getJson('/api/public/site')
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'business' => ['name', 'tagline', 'description', 'hero_title', 'hero_subtitle', 'logo_url', 'favicon_url'],
                'contact' => ['address', 'whatsapp', 'whatsapp_url', 'operating_hours', 'social_links'],
                'about' => ['vision', 'missions'],
                'colors' => ['primary', 'secondary', 'accent', 'background', 'text'],
                'packages',
                'procedures',
                'faqs',
            ]])
            ->assertJsonPath('data.business.name', config('app.name'))
            ->assertJsonPath('data.colors.primary', '#ADD8E6')
            ->assertJsonPath('data.colors.secondary', '#ADBCE6')
            ->assertJsonPath('data.colors.accent', '#ADE6D8');
    }

    public function test_site_does_not_leak_internal_data(): void
    {
        $this->setting('logo_path', 'branding/'.str_repeat('a', 40).'.png', 'branding', 'image');

        $body = $this->getJson('/api/public/site')->assertOk()->getContent();

        foreach (['logo_path', 'password', 'email', 'remember_token'] as $secret) {
            $this->assertStringNotContainsString($secret, $body);
        }

        $this->assertStringContainsString('/api/public/media/'.str_repeat('a', 40).'.png', $body);
    }

    public function test_only_active_packages_are_listed_in_order(): void
    {
        LaundryPackage::factory()->create(['name' => 'Kedua', 'sort_order' => 2, 'estimated_hours' => 36, 'features' => ['A', 'B']]);
        LaundryPackage::factory()->create(['name' => 'Pertama', 'sort_order' => 1, 'estimated_hours' => 48, 'price' => 8000]);
        LaundryPackage::factory()->inactive()->create(['name' => 'Nonaktif']);
        LaundryPackage::factory()->create(['name' => 'Arsip'])->delete();

        $response = $this->getJson('/api/public/site')->assertOk();

        $this->assertSame(['Pertama', 'Kedua'], collect($response->json('data.packages'))->pluck('name')->all());

        $first = $response->json('data.packages.0');
        $this->assertEquals(8000, $first['price']);
        $this->assertSame('kg', $first['unit']);
        $this->assertSame('per kg', $first['price_label']);
        $this->assertSame('2 hari', $first['estimated_label']);

        $second = $response->json('data.packages.1');
        $this->assertSame('36 jam', $second['estimated_label']);
        $this->assertSame(['A', 'B'], $second['features']);
    }

    public function test_per_item_packages_use_item_wording(): void
    {
        LaundryPackage::factory()->perItem()->create(['name' => 'Bedcover', 'price' => 30000, 'estimated_hours' => 24]);

        $this->getJson('/api/public/site')
            ->assertOk()
            ->assertJsonPath('data.packages.0.unit', 'item')
            ->assertJsonPath('data.packages.0.price_label', 'per item')
            ->assertJsonPath('data.packages.0.estimated_label', '1 hari');
    }

    public function test_procedures_and_faqs_are_ordered_and_hide_inactive_entries(): void
    {
        LaundryProcedure::create(['title' => 'Langkah 2', 'sort_order' => 2, 'is_active' => true]);
        LaundryProcedure::create(['title' => 'Langkah 1', 'sort_order' => 1, 'is_active' => true, 'icon' => 'shirt']);
        LaundryProcedure::create(['title' => 'Tersembunyi', 'sort_order' => 3, 'is_active' => false]);

        LaundryFaq::create(['question' => 'Tanya 2', 'answer' => 'Jawab 2', 'sort_order' => 2, 'is_active' => true]);
        LaundryFaq::create(['question' => 'Tanya 1', 'answer' => 'Jawab 1', 'sort_order' => 1, 'is_active' => true]);
        LaundryFaq::create(['question' => 'Rahasia', 'answer' => 'Jawab', 'sort_order' => 3, 'is_active' => false]);

        $response = $this->getJson('/api/public/site')->assertOk();

        $this->assertSame(['Langkah 1', 'Langkah 2'], collect($response->json('data.procedures'))->pluck('title')->all());
        $this->assertSame('shirt', $response->json('data.procedures.0.icon'));
        $this->assertSame(['Tanya 1', 'Tanya 2'], collect($response->json('data.faqs'))->pluck('question')->all());
    }

    public function test_contact_about_and_social_links(): void
    {
        $this->setting('whatsapp', '0812-3456-7890', 'contact');
        $this->setting('address', 'Jl. Contoh No. 7, Makassar', 'contact', 'textarea');
        $this->setting('operating_hours', '08.00 - 20.00 WITA', 'contact');
        $this->setting('social_links', json_encode([
            'instagram' => 'https://instagram.com/uji', 'facebook' => '', 'tiktok' => '', 'youtube' => '',
        ]), 'contact', 'json');
        $this->setting('vision', 'Visi uji', 'about', 'textarea');
        $this->setting('missions', json_encode(['Misi 1', 'Misi 2']), 'about', 'json');

        $this->getJson('/api/public/site')
            ->assertOk()
            ->assertJsonPath('data.contact.whatsapp', '6281234567890')
            ->assertJsonPath('data.contact.whatsapp_url', 'https://wa.me/6281234567890')
            ->assertJsonPath('data.contact.address', 'Jl. Contoh No. 7, Makassar')
            ->assertJsonPath('data.contact.operating_hours', '08.00 - 20.00 WITA')
            ->assertJsonPath('data.contact.social_links.instagram', 'https://instagram.com/uji')
            ->assertJsonMissingPath('data.contact.social_links.facebook')
            ->assertJsonPath('data.about.vision', 'Visi uji')
            ->assertJsonPath('data.about.missions', ['Misi 1', 'Misi 2']);
    }

    public function test_invalid_stored_colors_fall_back_to_defaults(): void
    {
        LaundryColor::create(['key' => 'primary', 'label' => 'Primary', 'value' => '#GGGGGG', 'default_value' => '#ADD8E6']);
        LaundryColor::create(['key' => 'accent', 'label' => 'Accent', 'value' => '#FFE08A', 'default_value' => '#ADE6D8']);

        $this->getJson('/api/public/site')
            ->assertOk()
            ->assertJsonPath('data.colors.primary', '#ADD8E6')
            ->assertJsonPath('data.colors.accent', '#FFE08A');
    }

    public function test_site_endpoint_is_rate_limited(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/api/public/site')->assertOk();
        }

        $this->getJson('/api/public/site')->assertStatus(429);
    }

    /* ------------------------------ Shell SPA ------------------------------ */

    public function test_spa_shell_embeds_theme_title_and_favicon_from_the_database(): void
    {
        $favicon = 'branding/'.str_repeat('a', 40).'.png';

        LaundryColor::create(['key' => 'primary', 'label' => 'Primary', 'value' => '#F4A6C0', 'default_value' => '#ADD8E6']);
        $this->setting('business_name', 'Laundry Uji');
        $this->setting('favicon_path', $favicon, 'branding', 'image');

        $this->get('/')
            ->assertOk()
            ->assertSee('<title>Laundry Uji</title>', false)
            ->assertSee('<style id="site-theme">html:root{--color-primary:#F4A6C0;', false)
            ->assertSee('<link rel="icon" href="/api/public/media/'.str_repeat('a', 40).'.png">', false)
            ->assertSee('<meta name="theme-color" content="#F4A6C0">', false);
    }

    public function test_spa_shell_escapes_content_and_ignores_invalid_colors(): void
    {
        $this->setting('business_name', '</title><script>alert(1)</script>');
        LaundryColor::create(['key' => 'primary', 'label' => 'Primary', 'value' => '#GGGGGG', 'default_value' => '#ADD8E6']);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('GGGGGG', false)
            ->assertSee('--color-primary:#ADD8E6', false);
    }

    /* ------------------------------ Media ------------------------------ */

    public function test_media_endpoint_only_serves_uploaded_branding_files(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('branding/'.str_repeat('b', 40).'.png', 'isi-gambar');

        $this->get('/api/public/media/'.str_repeat('b', 40).'.png')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        foreach ([
            str_repeat('c', 40).'.png',      // tidak ada
            str_repeat('b', 40).'.svg',      // ekstensi tidak diizinkan
            str_repeat('b', 39).'.png',      // nama terlalu pendek
            'logo.png',                      // bukan nama hasil unggahan
            '..%2F..%2F.env',                // percobaan path traversal
        ] as $name) {
            $this->get('/api/public/media/'.$name)->assertNotFound();
        }
    }
}