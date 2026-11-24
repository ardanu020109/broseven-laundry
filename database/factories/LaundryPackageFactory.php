<?php

namespace Database\Factories;

use App\Enums\PackageUnit;
use App\Models\LaundryPackage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LaundryPackage>
 */
class LaundryPackageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Paket '.fake()->unique()->word(),
            'description' => fake()->sentence(),
            'price' => fake()->randomElement([6000, 8000, 10000, 12000, 15000]),
            'unit' => PackageUnit::Kg,
            'estimated_hours' => fake()->randomElement([24, 48, 72]),
            'features' => ['Deterjen berkualitas', 'Pewangi', 'Dilipat rapi'],
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function perItem(): static
    {
        return $this->state(fn () => ['unit' => PackageUnit::Item]);
    }
}