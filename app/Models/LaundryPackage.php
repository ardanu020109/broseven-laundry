<?php

namespace App\Models;

use App\Enums\PackageUnit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LaundryPackage extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'laundry_packages';

    protected $fillable = [
        'name',
        'description',
        'price',
        'unit',
        'estimated_hours',
        'features',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'unit' => PackageUnit::class,
            'estimated_hours' => 'integer',
            'features' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(LaundryTransaction::class, 'package_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}