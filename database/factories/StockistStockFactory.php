<?php

namespace Database\Factories;

use App\Models\Stockist;
use App\Models\StockistStock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockistStock>
 */
class StockistStockFactory extends Factory
{
    protected $model = StockistStock::class;

    public function definition(): array
    {
        return [
            'stockist_id' => Stockist::factory(),
            'product_name' => fake()->randomElement(['Ora herbal mix', 'African bitters', 'Immune booster']),
            'grammage' => fake()->randomElement([100, 200, 500]),
            'quantity' => fake()->numberBetween(1, 100),
        ];
    }
}
