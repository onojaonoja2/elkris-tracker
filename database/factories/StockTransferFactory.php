<?php

namespace Database\Factories;

use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockTransfer>
 */
class StockTransferFactory extends Factory
{
    protected $model = StockTransfer::class;

    public function definition(): array
    {
        return [
            'from_warehouse_id' => Warehouse::factory(),
            'to_agent_id' => User::factory()->communitySalesRepresentative(),
            'status' => 'draft',
            'requested_by' => User::factory(),
        ];
    }
}
