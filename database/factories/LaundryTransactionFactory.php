<?php

namespace Database\Factories;

use App\Enums\TransactionStatus;
use App\Models\LaundryPackage;
use App\Models\LaundryTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LaundryTransaction>
 */
class LaundryTransactionFactory extends Factory
{
    public function definition(): array
    {
        $package = LaundryPackage::query()->inRandomOrder()->first()
            ?? LaundryPackage::factory()->create();

        $receivedAt = now()->subHours(fake()->numberBetween(1, 72));

        return [
            'code' => 'BS-'.$receivedAt->format('ymd').'-'.fake()->unique()->numerify('####'),
            'tracking_token' => Str::random(40),
            'customer_name' => fake()->name(),
            'customer_phone' => '62812'.fake()->numerify('#######'),
            'package_id' => $package->id,
            'package_name' => $package->name,
            'package_unit' => $package->unit,
            'package_price' => $package->price,
            'quantity' => fake()->randomFloat(2, 1, 10),
            'total_price' => 0, // dihitung ulang di configure()
            'status' => TransactionStatus::Received,
            'notes' => null,
            'received_at' => $receivedAt,
            'estimated_completion_at' => $receivedAt->copy()->addHours($package->estimated_hours),
            'received_by' => User::query()->active()->inRandomOrder()->value('id')
                ?? User::factory(),
        ];
    }

    public function configure(): static
    {
        // Total selalu dihitung dari snapshot harga x jumlah, tidak diisi manual.
        return $this->afterMaking(function (LaundryTransaction $transaction) {
            $transaction->total_price = round(
                (float) $transaction->package_price * (float) $transaction->quantity,
                2
            );
        });
    }

    public function forPackage(LaundryPackage $package): static
    {
        return $this->state(fn () => [
            'package_id' => $package->id,
            'package_name' => $package->name,
            'package_unit' => $package->unit,
            'package_price' => $package->price,
            'estimated_completion_at' => now()->addHours($package->estimated_hours),
        ]);
    }

    public function status(TransactionStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}