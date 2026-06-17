<?php

namespace Database\Factories;

use App\Models\ProductType;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockTransferItem>
 */
class StockTransferItemFactory extends Factory
{
    protected $model = StockTransferItem::class;

    public function definition(): array
    {
        return [
            'stock_transfer_id' => StockTransfer::factory(),
            'product_type_id' => ProductType::factory(),
            'grammage' => fake()->randomElement([100, 200, 500]),
            'quantity' => fake()->numberBetween(1, 50),
            'rejected_quantity' => 0,
        ];
    }
}
