<?php

namespace App\Services;

use App\Models\User;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Foto profil: dipotong persegi, diubah ke 256x256, lalu disimpan ulang sebagai PNG.
 * Penyimpanan ulang menghapus metadata (mis. lokasi GPS) dan isi tersembunyi dalam file.
 * File disimpan di disk privat dan hanya disajikan lewat route yang membutuhkan login.
 */
class AvatarService
{
    public const SIZE = 256;

    private const DISK = 'local';

    /** @return string path relatif, mis. avatars/<40 karakter>.png */
    public function store(UploadedFile $file): string
    {
        $contents = @file_get_contents($file->getRealPath());
        $source = $contents === false ? false : @imagecreatefromstring($contents);

        if (! $source instanceof GdImage) {
            throw ValidationException::withMessages([
                'photo' => 'File gambar tidak dapat dibaca. Gunakan file PNG, JPG, atau WEBP yang valid.',
            ]);
        }

        $source = $this->orient($source, $file);

        $width = imagesx($source);
        $height = imagesy($source);
        $side = min($width, $height);

        $canvas = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 255, 255, 255, 127));
        imagecopyresampled(
            $canvas, $source,
            0, 0, intdiv($width - $side, 2), intdiv($height - $side, 2),
            self::SIZE, self::SIZE, $side, $side
        );

        ob_start();
        imagepng($canvas, null, 6);
        $png = (string) ob_get_clean();

        $path = 'avatars/'.Str::random(40).'.png';

        if ($png === '' || ! Storage::disk(self::DISK)->put($path, $png)) {
            throw ValidationException::withMessages([
                'photo' => 'Foto gagal disimpan. Silakan coba lagi.',
            ]);
        }

        return $path;
    }

    /** Hanya file hasil unggahan aplikasi yang boleh dihapus. */
    public function delete(?string $path): void
    {
        if ($path !== null && preg_match(User::AVATAR_PATTERN, $path)) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    /** Memutar foto JPEG dari ponsel sesuai orientasi EXIF (jika ekstensi exif aktif). */
    private function orient(GdImage $image, UploadedFile $file): GdImage
    {
        if (! function_exists('exif_read_data') || $file->getMimeType() !== 'image/jpeg') {
            return $image;
        }

        $exif = @exif_read_data($file->getRealPath());

        $angle = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);

        return $rotated instanceof GdImage ? $rotated : $image;
    }
}