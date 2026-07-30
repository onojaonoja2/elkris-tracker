<?php

namespace Database\Factories;

use App\Models\ProductType;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseReturn;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WarehouseReturn>
 */
class WarehouseReturnFactory extends Factory
{
    protected $model = WarehouseReturn::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'warehouse_id' => Warehouse::factory(),
            'product_type_id' => ProductType::factory(),
            'grammage' => 100,
            'quantity' => 10,
            'reason' => fake()->sentence(),
            'status' => 'pending',
        ];
    }
}
