<?php

namespace Database\Factories;

use App\Models\Stockist;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Stockist>
 */
class StockistFactory extends Factory
{
    protected $model = Stockist::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'phone' => fake()->numerify('###########'),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'region' => 'South West',
            'address' => fake()->address(),
            'stock_balance' => 0,
            'created_by' => null,
            'supervisor_id' => null,
        ];
    }
}
