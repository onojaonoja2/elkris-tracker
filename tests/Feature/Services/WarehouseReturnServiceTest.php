<?php

namespace Tests\Feature\Services;

use App\Models\AgentStock;
use App\Models\Inventory;
use App\Models\ProductType;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseReturn;
use App\Services\WarehouseReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WarehouseReturnServiceTest extends TestCase
{
    use RefreshDatabase;

    private function setupAgentStock(User $agent, ProductType $productType, int $grammage, int $quantity): AgentStock
    {
        return AgentStock::factory()->create([
            'user_id' => $agent->id,
            'product_type_id' => $productType->id,
            'product_name' => $productType->name,
            'grammage' => $grammage,
            'quantity' => $quantity,
        ]);
    }

    public function test_submit_creates_pending_return_when_stock_available(): void
    {
        $agent = User::factory()->communitySalesRepresentative()->create();
        $warehouse = Warehouse::factory()->create();
        $productType = ProductType::factory()->create();
        $this->setupAgentStock($agent, $productType, 100, 50);

        $return = WarehouseReturnService::submit([
            'warehouse_id' => $warehouse->id,
            'product_type_id' => $productType->id,
            'grammage' => 100,
            'quantity' => 20,
            'reason' => 'Unsold stock',
        ], $agent->id);

        $this->assertInstanceOf(WarehouseReturn::class, $return);
        $this->assertEquals('pending', $return->status);
        $this->assertEquals(20, $return->quantity);
    }

    public function test_submit_fails_when_insufficient_stock(): void
    {
        $agent = User::factory()->communitySalesRepresentative()->create();
        $warehouse = Warehouse::factory()->create();
        $productType = ProductType::factory()->create();
        $this->setupAgentStock($agent, $productType, 100, 5);

        $this->expectException(ValidationException::class);
        WarehouseReturnService::submit([
            'warehouse_id' => $warehouse->id,
            'product_type_id' => $productType->id,
            'grammage' => 100,
            'quantity' => 20,
        ], $agent->id);
    }

    public function test_approve_deducts_agent_stock_and_credits_warehouse_inventory(): void
    {
        $agent = User::factory()->communitySalesRepresentative()->create();
        $warehouse = Warehouse::factory()->create();
        $productType = ProductType::factory()->create();
        $this->setupAgentStock($agent, $productType, 100, 50);

        $return = WarehouseReturnService::submit([
            'warehouse_id' => $warehouse->id,
            'product_type_id' => $productType->id,
            'grammage' => 100,
            'quantity' => 20,
        ], $agent->id);

        $manager = User::factory()->warehouseManager()->create();
        WarehouseReturnService::approve($return, $manager->id);

        $return->refresh();
        $this->assertEquals('approved', $return->status);
        $this->assertEquals($manager->id, $return->approved_by);

        $agentStock = AgentStock::where([
            'user_id' => $agent->id,
            'product_type_id' => $productType->id,
            'grammage' => 100,
        ])->first();
        $this->assertEquals(30, $agentStock->quantity);

        $inventory = Inventory::where([
            'warehouse_id' => $warehouse->id,
            'product_type_id' => $productType->id,
            'grammage' => 100,
        ])->first();
        $this->assertNotNull($inventory);
        $this->assertEquals(20, $inventory->quantity);
    }

    public function test_approve_fails_when_agent_stock_is_insufficient(): void
    {
        $agent = User::factory()->communitySalesRepresentative()->create();
        $warehouse = Warehouse::factory()->create();
        $productType = ProductType::factory()->create();
        $this->setupAgentStock($agent, $productType, 100, 50);

        $return = WarehouseReturnService::submit([
            'warehouse_id' => $warehouse->id,
            'product_type_id' => $productType->id,
            'grammage' => 100,
            'quantity' => 20,
        ], $agent->id);

        AgentStock::where([
            'user_id' => $agent->id,
            'product_type_id' => $productType->id,
            'grammage' => 100,
        ])->decrement('quantity', 40);

        $manager = User::factory()->warehouseManager()->create();

        $this->expectException(ValidationException::class);
        WarehouseReturnService::approve($return, $manager->id);
    }

    public function test_reject_updates_status_and_reason(): void
    {
        $agent = User::factory()->communitySalesRepresentative()->create();
        $warehouse = Warehouse::factory()->create();
        $productType = ProductType::factory()->create();
        $this->setupAgentStock($agent, $productType, 100, 50);

        $return = WarehouseReturnService::submit([
            'warehouse_id' => $warehouse->id,
            'product_type_id' => $productType->id,
            'grammage' => 100,
            'quantity' => 20,
        ], $agent->id);

        $manager = User::factory()->warehouseManager()->create();
        WarehouseReturnService::reject($return, 'Damaged items not eligible', $manager->id);

        $return->refresh();
        $this->assertEquals('rejected', $return->status);
        $this->assertEquals('Damaged items not eligible', $return->rejection_reason);
        $this->assertEquals($manager->id, $return->approved_by);
    }

    public function test_non_pending_return_cannot_be_approved(): void
    {
        $agent = User::factory()->communitySalesRepresentative()->create();
        $warehouse = Warehouse::factory()->create();
        $productType = ProductType::factory()->create();
        $this->setupAgentStock($agent, $productType, 100, 50);

        $return = WarehouseReturn::factory()->create([
            'user_id' => $agent->id,
            'warehouse_id' => $warehouse->id,
            'product_type_id' => $productType->id,
            'grammage' => 100,
            'quantity' => 20,
            'status' => 'approved',
        ]);

        $this->expectException(ValidationException::class);
        WarehouseReturnService::approve($return, User::factory()->warehouseManager()->create()->id);
    }
}
