<?php

namespace Database\Factories;

use App\Models\SalesRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesRecord>
 */
class SalesRecordFactory extends Factory
{
    protected $model = SalesRecord::class;

    public function definition(): array
    {
        return [
            'agent_id' => User::factory(),
            'products' => [
                [
                    'product_name' => fake()->randomElement(['Ora herbal mix', 'African bitters', 'Immune booster']),
                    'grammage' => fake()->randomElement(['100g', '200g', '500g']),
                    'quantity' => fake()->numberBetween(1, 10),
                ],
            ],
            'total_value' => 500,
            'status' => 'pending',
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => 'approved',
            'accountant_verified_at' => now(),
        ]);
    }

    public function credit(): static
    {
        return $this->state(fn (): array => [
            'is_credit' => true,
            'credit_status' => 'pending_payment',
            'status' => 'approved',
            'accountant_verified_at' => now(),
            'expected_collection_date' => now()->addDays(7)->toDateString(),
        ]);
    }

    public function collected(): static
    {
        return $this->credit()->state(fn (): array => [
            'credit_status' => 'collected',
            'collected_at' => now(),
        ]);
    }

    public function partiallyCollected(): static
    {
        return $this->credit()->state(fn (): array => [
            'credit_status' => 'partially_collected',
        ]);
    }

    public function overdue(): static
    {
        return $this->credit()->state(fn (): array => [
            'expected_collection_date' => now()->subDays(3)->toDateString(),
        ]);
    }
}
