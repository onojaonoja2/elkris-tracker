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
}
