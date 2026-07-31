<?php

namespace Database\Factories;

use App\Models\DamagedInventory;
use App\Models\DamagedStockReturn;
use App\Models\ProductType;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DamagedInventory>
 */
class DamagedInventoryFactory extends Factory
{
    protected $model = DamagedInventory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'damaged_stock_return_id' => DamagedStockReturn::factory(),
            'warehouse_id' => Warehouse::factory(),
            'product_type_id' => ProductType::factory(),
            'grammage' => 100,
            'quantity' => fake()->numberBetween(1, 50),
            'status' => 'in_stock',
        ];
    }

    public function dispatchedTo(int $destinationWarehouseId): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'dispatched',
            'destination_warehouse_id' => $destinationWarehouseId,
        ]);
    }

    public function destroyed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'destroyed',
            'destroyed_at' => now(),
        ]);
    }
}
