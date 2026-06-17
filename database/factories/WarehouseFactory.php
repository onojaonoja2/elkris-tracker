<?php

namespace Database\Factories;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Warehouse>
 */
class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company().' Warehouse',
            'type' => 'state',
            'phone' => fake()->numerify('###########'),
            'address' => fake()->address(),
            'state_id' => null,
            'is_active' => true,
        ];
    }
}
