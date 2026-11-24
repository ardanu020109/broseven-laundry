<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class LaundryWebsiteSetting extends Model
{
    protected $table = 'laundry_website_settings';

    protected $fillable = [
        'group',
        'key',
        'value',
        'type',
    ];

    /**
     * Nilai yang sudah dikonversi sesuai kolom type
     * (json => array, boolean => bool, selain itu string).
     */
    protected function typedValue(): Attribute
    {
        return Attribute::get(function () {
            $value = $this->value;

            return match ($this->type) {
                'json' => filled($value) ? (json_decode($value, true) ?? []) : [],
                'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                default => $value,
            };
        });
    }
}