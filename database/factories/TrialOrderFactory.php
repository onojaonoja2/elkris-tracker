<?php

namespace Database\Factories;

use App\Models\TrialOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrialOrder>
 */
class TrialOrderFactory extends Factory
{
    protected $model = TrialOrder::class;

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
            'total_value' => 1000,
            'status' => 'pending',
            'payment_status' => 'pending',
        ];
    }
}
