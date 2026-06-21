<?php

namespace Database\Factories;

use App\Models\Inventory;
use App\Models\ProductType;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inventory>
 */
class InventoryFactory extends Factory
{
    protected $model = Inventory::class;

    public function definition(): array
    {
        return [
            'warehouse_id' => Warehouse::factory(),
            'product_type_id' => ProductType::factory(),
            'grammage' => fake()->randomElement([100, 200, 500]),
            'quantity' => fake()->numberBetween(1, 500),
        ];
    }
}
