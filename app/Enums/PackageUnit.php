<?php

namespace App\Enums;

enum PackageUnit: string
{
    case Kg = 'kg';
    case Item = 'item';

    public function label(): string
    {
        return match ($this) {
            self::Kg => 'Kilogram',
            self::Item => 'Item',
        };
    }

    /** Contoh: "per kg", "per item". */
    public function priceLabel(): string
    {
        return 'per '.$this->value;
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}