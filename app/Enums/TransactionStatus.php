<?php

namespace App\Enums;

enum TransactionStatus: string
{
    case Received = 'RECEIVED';
    case Washing = 'WASHING';
    case Drying = 'DRYING';
    case Ironing = 'IRONING';
    case Ready = 'READY';
    case Completed = 'COMPLETED';
    case Cancelled = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Diterima',
            self::Washing => 'Sedang dicuci',
            self::Drying => 'Sedang dikeringkan',
            self::Ironing => 'Sedang disetrika',
            self::Ready => 'Siap diambil',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
        };
    }

    /** Nama varian warna badge di frontend. */
    public function tone(): string
    {
        return match ($this) {
            self::Received => 'neutral',
            self::Washing, self::Drying, self::Ironing => 'info',
            self::Ready => 'warning',
            self::Completed => 'success',
            self::Cancelled => 'danger',
        };
    }

    /**
     * Status tujuan yang diizinkan dari status ini.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Received => [self::Washing, self::Cancelled],
            self::Washing => [self::Drying, self::Cancelled],
            self::Drying => [self::Ironing, self::Ready, self::Cancelled],
            self::Ironing => [self::Ready, self::Cancelled],
            self::Ready => [self::Completed, self::Cancelled],
            self::Completed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isFinal(): bool
    {
        return $this === self::Completed || $this === self::Cancelled;
    }

    /**
     * Status yang dihitung sebagai "cucian dalam proses".
     *
     * @return array<int, self>
     */
    public static function inProgress(): array
    {
        return [self::Received, self::Washing, self::Drying, self::Ironing];
    }

    /**
     * Urutan tahap normal (dipakai timeline tracking publik).
     *
     * @return array<int, self>
     */
    public static function timeline(): array
    {
        return [
            self::Received,
            self::Washing,
            self::Drying,
            self::Ironing,
            self::Ready,
            self::Completed,
        ];
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}