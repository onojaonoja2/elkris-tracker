<?php

namespace Database\Factories;

use App\Models\AgentStock;
use App\Models\ProductType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentStock>
 */
class AgentStockFactory extends Factory
{
    protected $model = AgentStock::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'product_type_id' => ProductType::factory(),
            'product_name' => fake()->randomElement(['Ora herbal mix', 'African bitters', 'Immune booster']),
            'grammage' => fake()->randomElement([100, 200, 500]),
            'quantity' => fake()->numberBetween(1, 50),
        ];
    }
}
