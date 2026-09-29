<?php

namespace Tests\Feature\Sales;

use App\Filament\Pages\SalesOrdersDashboard;
use App\Models\AgentStock;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\ProductType;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\NewSubmissionNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SalesWarehouseFulfillmentTest extends TestCase
{
    use RefreshDatabase;

    private function makeWarehouseWithStock(User $sales, int $quantity): Warehouse
    {
        $manager = User::factory()->warehouseManager()->create();

        $warehouse = Warehouse::factory()->create([
            'manager_id' => $manager->id,
            'sales_person_id' => $sales->id,
        ]);

        $this->productType = ProductType::factory()->create([
            'name' => 'Ora Herbal Mix',
            'available_grammages' => [100, 200],
        ]);

        Inventory::factory()->create([
            'warehouse_id' => $warehouse->id,
            'product_type_id' => $this->productType->id,
            'grammage' => 100,
            'quantity' => $quantity,
        ]);

        return $warehouse;
    }

    private ProductType $productType;

    public function test_sales_office_sale_from_warehouse_creates_record_transfer_and_manager_notification(): void
    {
        $sales = User::factory()->sales()->create();
        $warehouse = $this->makeWarehouseWithStock($sales, 50);

        $this->actingAs($sales);

        Livewire::test(SalesOrdersDashboard::class)
            ->mountAction('recordOfficeSale')
            ->fillForm([
                'product_type_id' => $this->productType->id,
                'grammage' => '100',
                'quantity' => 10,
                'price' => 500,
                'stock_source' => 'warehouse',
                'warehouse_id' => $warehouse->id,
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('sales_records', [
            'agent_id' => $sales->id,
            'agent_type' => 'sales',
            'stock_source' => 'warehouse',
            'warehouse_id' => $warehouse->id,
            'status' => 'pending',
            'stock_deducted_at' => null,
        ]);

        $this->assertDatabaseHas('stock_transfers', [
            'from_warehouse_id' => $warehouse->id,
            'to_agent_id' => $sales->id,
            'requested_by' => $sales->id,
            'status' => 'requested',
            'source_type' => 'sales_record',
        ]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $warehouse->manager_id,
            'type' => NewSubmissionNotification::class,
        ]);
    }

    public function test_sales_office_sale_from_warehouse_blocked_when_inventory_insufficient(): void
    {
        $sales = User::factory()->sales()->create();
        $warehouse = $this->makeWarehouseWithStock($sales, 5);

        $this->actingAs($sales);

        Livewire::test(SalesOrdersDashboard::class)
            ->mountAction('recordOfficeSale')
            ->fillForm([
                'product_type_id' => $this->productType->id,
                'grammage' => '100',
                'quantity' => 10,
                'price' => 500,
                'stock_source' => 'warehouse',
                'warehouse_id' => $warehouse->id,
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertDatabaseMissing('sales_records', ['agent_id' => $sales->id]);
        $this->assertDatabaseMissing('stock_transfers', ['from_warehouse_id' => $warehouse->id]);
    }

    public function test_sales_office_sale_from_stock_at_hand_deducts_held_stock(): void
    {
        $sales = User::factory()->sales()->create();
        $warehouse = $this->makeWarehouseWithStock($sales, 50);

        AgentStock::create([
            'user_id' => $sales->id,
            'product_type_id' => $this->productType->id,
            'product_name' => $this->productType->name,
            'grammage' => 100,
            'quantity' => 10,
        ]);

        $this->actingAs($sales);

        Livewire::test(SalesOrdersDashboard::class)
            ->mountAction('recordOfficeSale')
            ->fillForm([
                'product_type_id' => $this->productType->id,
                'grammage' => '100',
                'quantity' => 4,
                'price' => 500,
                'stock_source' => 'held',
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('agent_stocks', [
            'user_id' => $sales->id,
            'product_type_id' => $this->productType->id,
            'grammage' => 100,
            'quantity' => 6,
        ]);

        $this->assertDatabaseMissing('stock_transfers', ['from_warehouse_id' => $warehouse->id]);
    }

    public function test_sales_warehouse_order_creates_order_and_dispatch_request(): void
    {
        $sales = User::factory()->sales()->create();
        $warehouse = $this->makeWarehouseWithStock($sales, 50);
        $customer = Customer::factory()->create(['customer_name' => 'Chidi Buyer']);

        $this->actingAs($sales);

        Livewire::test(SalesOrdersDashboard::class)
            ->mountAction('initiateOrder')
            ->fillForm([
                'customer_id' => $customer->id,
                'stock_source' => 'warehouse',
                'warehouse_id' => $warehouse->id,
            ])
            ->set('mountedActions.0.data.items', [[
                'product_type_id' => $this->productType->id,
                'grammage' => '100',
                'quantity' => 4,
                'price' => 250,
            ]])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('orders', [
            'customer_id' => $customer->id,
            'user_id' => $sales->id,
            'stock_source' => 'warehouse',
            'warehouse_id' => $warehouse->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('stock_transfers', [
            'from_warehouse_id' => $warehouse->id,
            'to_agent_id' => $sales->id,
            'requested_by' => $sales->id,
            'status' => 'requested',
            'source_type' => 'sales_order',
        ]);

        $this->assertDatabaseHas('stock_transfer_items', [
            'product_type_id' => $this->productType->id,
            'grammage' => 100,
            'quantity' => 4,
        ]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $warehouse->manager_id,
            'type' => NewSubmissionNotification::class,
        ]);
    }

    public function test_sales_warehouse_order_blocked_when_inventory_insufficient(): void
    {
        $sales = User::factory()->sales()->create();
        $warehouse = $this->makeWarehouseWithStock($sales, 2);
        $customer = Customer::factory()->create();

        $this->actingAs($sales);

        Livewire::test(SalesOrdersDashboard::class)
            ->mountAction('initiateOrder')
            ->fillForm([
                'customer_id' => $customer->id,
                'stock_source' => 'warehouse',
                'warehouse_id' => $warehouse->id,
            ])
            ->set('mountedActions.0.data.items', [[
                'product_type_id' => $this->productType->id,
                'grammage' => '100',
                'quantity' => 4,
                'price' => 250,
            ]])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertDatabaseMissing('orders', ['customer_id' => $customer->id]);
        $this->assertDatabaseMissing('stock_transfers', ['from_warehouse_id' => $warehouse->id]);
    }
}
