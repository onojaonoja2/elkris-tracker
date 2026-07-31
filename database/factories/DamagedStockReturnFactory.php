<?php

namespace Database\Factories;

use App\Models\DamagedStockReturn;
use App\Models\ProductType;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DamagedStockReturn>
 */
class DamagedStockReturnFactory extends Factory
{
    protected $model = DamagedStockReturn::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'warehouse_id' => Warehouse::factory(),
            'product_type_id' => ProductType::factory(),
            'grammage' => 100,
            'quantity' => fake()->numberBetween(1, 50),
            'reason' => fake()->sentence(),
            'status' => 'pending',
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'supervisor_approved_by' => User::factory()->supervisor(),
            'supervisor_approved_at' => now(),
            'accountant_approved_by' => User::factory()->accountant(),
            'accountant_approved_at' => now(),
        ]);
    }

    public function returned(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'returned',
            'supervisor_approved_by' => User::factory()->supervisor(),
            'supervisor_approved_at' => now(),
            'accountant_approved_by' => User::factory()->accountant(),
            'accountant_approved_at' => now(),
            'return_to_warehouse_initiated_by' => User::factory()->warehouseManager(),
            'return_to_warehouse_initiated_at' => now(),
            'return_received_by' => User::factory()->warehouseManager(),
            'return_received_at' => now(),
        ]);
    }
}
