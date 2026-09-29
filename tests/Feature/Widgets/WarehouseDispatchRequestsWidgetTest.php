<?php

namespace Tests\Feature\Widgets;

use App\Filament\Widgets\WarehouseDispatchRequestsWidget;
use App\Models\Inventory;
use App\Models\ProductType;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class WarehouseDispatchRequestsWidgetTest extends TestCase
{
    use RefreshDatabase;

    private function makeDispatchRequest(User $manager, string $sourceType, int $quantity, int $stockQuantity): StockTransfer
    {
        $sales = User::factory()->sales()->create();
        $warehouse = Warehouse::factory()->create(['manager_id' => $manager->id]);
        $productType = ProductType::factory()->create(['available_grammages' => [100, 200]]);

        Inventory::factory()->create([
            'warehouse_id' => $warehouse->id,
            'product_type_id' => $productType->id,
            'grammage' => 100,
            'quantity' => $stockQuantity,
        ]);

        $transfer = StockTransfer::create([
            'from_warehouse_id' => $warehouse->id,
            'to_agent_id' => $sales->id,
            'requested_by' => $sales->id,
            'status' => 'requested',
            'source_type' => $sourceType,
            'source_name' => 'Order #1',
        ]);

        $transfer->items()->create([
            'product_type_id' => $productType->id,
            'grammage' => 100,
            'quantity' => $quantity,
        ]);

        return $transfer;
    }

    public function test_manager_dispatches_sales_order_request_from_dashboard(): void
    {
        $manager = User::factory()->warehouseManager()->create();
        $transfer = $this->makeDispatchRequest($manager, 'sales_order', 3, 10);

        $this->actingAs($manager);

        Livewire::test(WarehouseDispatchRequestsWidget::class)
            ->assertCanSeeTableRecords([$transfer])
            ->callTableAction('dispatch', $transfer->id);

        $this->assertDatabaseHas('inventories', [
            'warehouse_id' => $transfer->from_warehouse_id,
            'product_type_id' => $transfer->items->first()->product_type_id,
            'grammage' => 100,
            'quantity' => 7,
        ]);

        $this->assertDatabaseHas('stock_transfers', [
            'id' => $transfer->id,
            'status' => 'dispatched',
            'dispatched_by' => $manager->id,
        ]);

        $this->assertDatabaseHas('agent_stocks', [
            'user_id' => $transfer->to_agent_id,
            'product_type_id' => $transfer->items->first()->product_type_id,
            'grammage' => 100,
            'quantity' => 3,
        ]);

        $this->assertDatabaseHas('stock_transactions', [
            'type' => 'disbursed',
            'warehouse_id' => $transfer->from_warehouse_id,
            'quantity' => 3,
        ]);
    }

    public function test_dispatching_sales_record_request_does_not_increment_agent_stock(): void
    {
        $manager = User::factory()->warehouseManager()->create();
        $transfer = $this->makeDispatchRequest($manager, 'sales_record', 3, 10);

        StockTransferService::dispatchByWarehouse($transfer, $manager->id);

        $this->assertDatabaseHas('inventories', [
            'warehouse_id' => $transfer->from_warehouse_id,
            'product_type_id' => $transfer->items->first()->product_type_id,
            'grammage' => 100,
            'quantity' => 7,
        ]);

        $this->assertDatabaseHas('stock_transfers', [
            'id' => $transfer->id,
            'status' => 'dispatched',
        ]);

        $this->assertDatabaseMissing('agent_stocks', ['user_id' => $transfer->to_agent_id]);
    }

    public function test_dispatch_blocked_when_inventory_insufficient(): void
    {
        $manager = User::factory()->warehouseManager()->create();
        $transfer = $this->makeDispatchRequest($manager, 'sales_order', 5, 2);

        try {
            StockTransferService::dispatchByWarehouse($transfer, $manager->id);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }

        $this->assertDatabaseHas('stock_transfers', [
            'id' => $transfer->id,
            'status' => 'requested',
        ]);

        $this->assertDatabaseHas('inventories', [
            'warehouse_id' => $transfer->from_warehouse_id,
            'product_type_id' => $transfer->items->first()->product_type_id,
            'grammage' => 100,
            'quantity' => 2,
        ]);
    }

    public function test_widget_only_shows_requested_transfers_from_managed_warehouses(): void
    {
        $manager = User::factory()->warehouseManager()->create();
        $transfer = $this->makeDispatchRequest($manager, 'sales_order', 2, 10);

        $otherWarehouse = Warehouse::factory()->create();
        $foreignTransfer = StockTransfer::create([
            'from_warehouse_id' => $otherWarehouse->id,
            'status' => 'requested',
            'source_type' => 'sales_order',
        ]);

        $this->actingAs($manager);

        Livewire::test(WarehouseDispatchRequestsWidget::class)
            ->assertCanSeeTableRecords([$transfer])
            ->assertCanNotSeeTableRecords([$foreignTransfer]);
    }
}
