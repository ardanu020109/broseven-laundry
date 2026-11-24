<?php

namespace App\Services;

use App\Models\LaundryColor;
use App\Models\LaundryFaq;
use App\Models\LaundryPackage;
use App\Models\LaundryProcedure;
use App\Models\LaundryWebsiteSetting;
use App\Models\User;
use App\Support\PhoneNumber;
use App\Support\ThemeColors;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

/**
 * Pengaturan website: identitas, kontak, visi misi, logo/favicon, warna,
 * serta data publik untuk website.
 */
class WebsiteSettingService
{
    /** key => [group, type] */
    public const DEFINITIONS = [
        'business_name' => ['business', 'text'],
        'tagline' => ['business', 'text'],
        'description' => ['business', 'textarea'],
        'hero_title' => ['business', 'text'],
        'hero_subtitle' => ['business', 'textarea'],
        'address' => ['contact', 'textarea'],
        'whatsapp' => ['contact', 'text'],
        'operating_hours' => ['contact', 'text'],
        'social_links' => ['contact', 'json'],
        'logo_path' => ['branding', 'image'],
        'favicon_path' => ['branding', 'image'],
        'vision' => ['about', 'textarea'],
        'missions' => ['about', 'json'],
    ];

    public const SOCIAL_KEYS = ['instagram', 'facebook', 'tiktok', 'youtube'];

    public const IMAGE_KEYS = ['logo' => 'logo_path', 'favicon' => 'favicon_path'];

    /** Hanya file hasil unggahan aplikasi yang boleh dihapus atau disajikan. */
    private const FILE_PATTERN = '~^branding/[A-Za-z0-9]{40}\.(?:png|jpg|jpeg|webp|ico)$~';

    public function __construct(private readonly ActivityLogService $activity)
    {
    }

    /* ---------------------------------------------------------------
     | Pengaturan teks
     | ------------------------------------------------------------- */

    /** @return array<int, string> */
    public function editableKeys(): array
    {
        return array_values(array_diff(array_keys(self::DEFINITIONS), array_values(self::IMAGE_KEYS)));
    }

    /**
     * Semua pengaturan dengan nilai terkonversi dan default.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $rows = LaundryWebsiteSetting::query()->pluck('value', 'key');
        $values = [];

        foreach (self::DEFINITIONS as $key => $definition) {
            $raw = $rows->get($key);

            $values[$key] = $definition[1] === 'json'
                ? $this->decode($key, $raw)
                : (filled($raw) ? (string) $raw : null);
        }

        if (! filled($values['business_name'])) {
            $values['business_name'] = (string) config('app.name');
        }

        return $values;
    }

    /** @return array<string, mixed> */
    public function settings(): array
    {
        $all = $this->all();

        return [
            'business_name' => $all['business_name'],
            'tagline' => $all['tagline'],
            'description' => $all['description'],
            'hero_title' => $all['hero_title'],
            'hero_subtitle' => $all['hero_subtitle'],
            'address' => $all['address'],
            'whatsapp' => $all['whatsapp'],
            'operating_hours' => $all['operating_hours'],
            'social_links' => $all['social_links'],
            'vision' => $all['vision'],
            'missions' => $all['missions'],
            'logo_url' => $this->imageUrl($all['logo_path']),
            'favicon_url' => $this->imageUrl($all['favicon_path']),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(array $data, User $actor): array
    {
        DB::transaction(function () use ($data, $actor) {
            $changed = [];

            foreach ($this->editableKeys() as $key) {
                if (! array_key_exists($key, $data)) {
                    continue;
                }

                $new = $this->encode($key, $data[$key]);
                $row = LaundryWebsiteSetting::firstOrNew(['key' => $key]);

                if ($row->exists && $row->value === $new) {
                    continue;
                }

                $row->forceFill([
                    'group' => self::DEFINITIONS[$key][0],
                    'type' => self::DEFINITIONS[$key][1],
                    'value' => $new,
                ])->save();

                $changed[] = $key;
            }

            if ($changed !== []) {
                $this->activity->log(
                    'website.updated',
                    'Memperbarui pengaturan website',
                    null,
                    ['fields' => $changed],
                    $actor
                );
            }
        });

        return $this->settings();
    }

    /* ---------------------------------------------------------------
     | Logo dan favicon
     | ------------------------------------------------------------- */

    /** @return array<string, mixed> */
    public function storeImage(string $kind, UploadedFile $file, User $actor): array
    {
        $key = $this->imageKey($kind);
        $path = $file->store('branding', 'public');

        if ($path === false) {
            throw ValidationException::withMessages(['image' => 'File gagal disimpan. Silakan coba lagi.']);
        }

        try {
            $old = DB::transaction(function () use ($key, $path, $kind, $actor) {
                $row = LaundryWebsiteSetting::firstOrNew(['key' => $key]);
                $previous = $row->value;

                $row->forceFill(['group' => 'branding', 'type' => 'image', 'value' => $path])->save();

                $this->activity->log(
                    'website.image_updated',
                    $kind === 'logo' ? 'Mengganti logo website' : 'Mengganti favicon website',
                    null,
                    ['kind' => $kind],
                    $actor
                );

                return $previous;
            });
        } catch (Throwable $exception) {
            $this->deleteFile($path);

            throw $exception;
        }

        $this->deleteFile($old);

        return $this->settings();
    }

    /** @return array<string, mixed> */
    public function removeImage(string $kind, User $actor): array
    {
        $key = $this->imageKey($kind);

        $old = DB::transaction(function () use ($key, $kind, $actor) {
            $row = LaundryWebsiteSetting::firstOrNew(['key' => $key]);
            $previous = $row->value;

            if ($previous === null) {
                return null;
            }

            $row->forceFill(['group' => 'branding', 'type' => 'image', 'value' => null])->save();

            $this->activity->log(
                'website.image_removed',
                $kind === 'logo' ? 'Menghapus logo website' : 'Menghapus favicon website',
                null,
                ['kind' => $kind],
                $actor
            );

            return $previous;
        });

        $this->deleteFile($old);

        return $this->settings();
    }

    /** URL gambar yang disajikan aplikasi, atau null jika tidak valid. */
    public function imageUrl(?string $path): ?string
    {
        if ($path === null || ! preg_match(self::FILE_PATTERN, $path)) {
            return null;
        }

        return '/api/public/media/'.basename($path);
    }

    /* ---------------------------------------------------------------
     | Warna
     | ------------------------------------------------------------- */

    /** @return array<string, string> */
    public function colors(): array
    {
        $stored = LaundryColor::query()->pluck('value', 'key');
        $colors = [];

        foreach (ThemeColors::DEFAULTS as $key => $default) {
            $colors[$key] = ThemeColors::normalize($stored->get($key)) ?? $default;
        }

        return $colors;
    }

    /** @return array<int, array<string, mixed>> */
    public function colorItems(): array
    {
        $current = $this->colors();
        $items = [];

        foreach (ThemeColors::DEFAULTS as $key => $default) {
            $items[] = [
                'key' => $key,
                'label' => ThemeColors::LABELS[$key],
                'value' => $current[$key],
                'default_value' => $default,
                'css_variable' => ThemeColors::variable($key),
                'is_default' => $current[$key] === $default,
            ];
        }

        return $items;
    }

    /**
     * @param  array<string, string>  $input
     * @return array<int, array<string, mixed>>
     */
    public function updateColors(array $input, User $actor): array
    {
        $current = $this->colors();

        DB::transaction(function () use ($input, $current, $actor) {
            $changes = [];

            foreach ($input as $key => $value) {
                $value = ThemeColors::normalize(is_string($value) ? $value : null);

                if (! array_key_exists($key, ThemeColors::DEFAULTS) || $value === null) {
                    continue;
                }

                $this->saveColor($key, $value);

                if ($current[$key] !== $value) {
                    $changes[$key] = ['from' => $current[$key], 'to' => $value];
                }
            }

            if ($changes !== []) {
                $this->activity->log('website.colors_updated', 'Mengubah warna website', null, ['changes' => $changes], $actor);
            }
        });

        return $this->colorItems();
    }

    /**
     * @param  array<int, string>|null  $keys  null = semua warna
     * @return array<int, array<string, mixed>>
     */
    public function resetColors(?array $keys, User $actor): array
    {
        $targets = $keys === null
            ? ThemeColors::keys()
            : array_values(array_intersect(ThemeColors::keys(), $keys));

        DB::transaction(function () use ($targets, $actor) {
            foreach ($targets as $key) {
                $this->saveColor($key, ThemeColors::DEFAULTS[$key]);
            }

            $this->activity->log(
                'website.colors_reset',
                'Mengembalikan warna website ke default',
                null,
                ['keys' => $targets],
                $actor
            );
        });

        return $this->colorItems();
    }

    private function saveColor(string $key, string $value): void
    {
        $row = LaundryColor::firstOrNew(['key' => $key]);

        $row->forceFill([
            'label' => ThemeColors::LABELS[$key],
            'value' => $value,
            'default_value' => ThemeColors::DEFAULTS[$key],
        ])->save();
    }

    /* ---------------------------------------------------------------
     | Data untuk website publik dan shell SPA
     | ------------------------------------------------------------- */

    /** @return array<string, mixed> */
    public function publicSite(): array
    {
        $all = $this->all();
        $whatsapp = filled($all['whatsapp']) ? PhoneNumber::normalize($all['whatsapp']) : null;

        return [
            'business' => [
                'name' => $all['business_name'],
                'tagline' => $all['tagline'],
                'description' => $all['description'],
                'hero_title' => $all['hero_title'],
                'hero_subtitle' => $all['hero_subtitle'],
                'logo_url' => $this->imageUrl($all['logo_path']),
                'favicon_url' => $this->imageUrl($all['favicon_path']),
            ],
            'contact' => [
                'address' => $all['address'],
                'whatsapp' => $whatsapp,
                'whatsapp_url' => $whatsapp ? 'https://wa.me/'.$whatsapp : null,
                'operating_hours' => $all['operating_hours'],
                'social_links' => array_filter($all['social_links'], fn ($url) => filled($url)),
            ],
            'about' => [
                'vision' => $all['vision'],
                'missions' => $all['missions'],
            ],
            'colors' => $this->colors(),
            'packages' => $this->publicPackages(),
            'procedures' => $this->publicProcedures(),
            'faqs' => $this->publicFaqs(),
        ];
    }

    /**
     * Data minimal untuk halaman pertama (judul, favicon, warna).
     * Jika tabel belum ada (mis. sebelum migrasi), memakai nilai default.
     *
     * @return array<string, mixed>
     */
    public function shell(): array
    {
        try {
            $all = $this->all();

            return $this->shellData(
                $all['business_name'],
                $all['description'],
                $this->imageUrl($all['logo_path']),
                $this->imageUrl($all['favicon_path']),
                $this->colors(),
            );
        } catch (QueryException) {
            return $this->shellData((string) config('app.name'), null, null, null, ThemeColors::DEFAULTS);
        }
    }

    /**
     * @param  array<string, string>  $colors
     * @return array<string, mixed>
     */
    private function shellData(string $name, ?string $description, ?string $logo, ?string $favicon, array $colors): array
    {
        return [
            'name' => $name,
            'description' => $description
                ? Str::limit(trim((string) preg_replace('/\s+/u', ' ', $description)), 160)
                : null,
            'favicon_url' => $favicon,
            'primary' => $colors['primary'],
            'css' => ThemeColors::css($colors),
            'bootstrap' => [
                'name' => $name,
                'logo_url' => $logo,
                'favicon_url' => $favicon,
                'colors' => $colors,
            ],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function publicPackages(): array
    {
        return LaundryPackage::query()->active()->ordered()->get()
            ->map(fn (LaundryPackage $package) => [
                'id' => $package->id,
                'name' => $package->name,
                'description' => $package->description,
                'price' => (float) $package->price,
                'unit' => $package->unit->value,
                'unit_label' => $package->unit->label(),
                'price_label' => $package->unit->priceLabel(),
                'estimated_hours' => $package->estimated_hours,
                'estimated_label' => $this->estimateLabel((int) $package->estimated_hours),
                'features' => $package->features ?? [],
            ])
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function publicProcedures(): array
    {
        return LaundryProcedure::query()->active()->ordered()->get()
            ->map(fn (LaundryProcedure $procedure) => [
                'id' => $procedure->id,
                'title' => $procedure->title,
                'description' => $procedure->description,
                'icon' => $procedure->icon,
            ])
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function publicFaqs(): array
    {
        return LaundryFaq::query()->active()->ordered()->get()
            ->map(fn (LaundryFaq $faq) => [
                'id' => $faq->id,
                'question' => $faq->question,
                'answer' => $faq->answer,
            ])
            ->values()
            ->all();
    }

    private function estimateLabel(int $hours): string
    {
        if ($hours >= 24 && $hours % 24 === 0) {
            return intdiv($hours, 24).' hari';
        }

        return $hours.' jam';
    }

    /* ---------------------------------------------------------------
     | Pembantu
     | ------------------------------------------------------------- */

    private function imageKey(string $kind): string
    {
        return self::IMAGE_KEYS[$kind]
            ?? throw new InvalidArgumentException("Jenis gambar tidak dikenal: {$kind}");
    }

    private function deleteFile(?string $path): void
    {
        if ($path !== null && preg_match(self::FILE_PATTERN, $path)) {
            Storage::disk('public')->delete($path);
        }
    }

    private function encode(string $key, mixed $value): ?string
    {
        if (self::DEFINITIONS[$key][1] === 'json') {
            $payload = $key === 'social_links' ? $this->cleanSocial($value) : $this->cleanMissions($value);

            return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $text = is_scalar($value) ? trim((string) $value) : '';

        return $text === '' ? null : $text;
    }

    /** @return array<string, string> */
    private function cleanSocial(mixed $value): array
    {
        $value = is_array($value) ? $value : [];
        $links = [];

        foreach (self::SOCIAL_KEYS as $social) {
            $links[$social] = is_string($value[$social] ?? null) ? trim($value[$social]) : '';
        }

        return $links;
    }

    /** @return array<int, string> */
    private function cleanMissions(mixed $value): array
    {
        $value = is_array($value) ? $value : [];

        return array_values(array_filter(
            array_map(fn ($item) => is_string($item) ? trim($item) : '', $value),
            fn (string $item) => $item !== ''
        ));
    }

    /** @return array<string, string>|array<int, string> */
    private function decode(string $key, mixed $raw): array
    {
        $data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        $data = is_array($data) ? $data : [];

        if ($key === 'social_links') {
            return $this->cleanSocial($data);
        }

        return $this->cleanMissions($data);
    }
}