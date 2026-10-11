<?php

namespace Tests\Feature\Sales;

use App\Enums\OrderStatus;
use App\Filament\Pages\SalesOrdersDashboard;
use App\Filament\Widgets\SalesPendingOrdersWidget;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\ProductType;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\OrderAssignmentService;
use App\Services\SalesRecordService;
use App\Services\StockTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SalesOrderWarehouseSourceTest extends TestCase
{
    use RefreshDatabase;

    private ProductType $productType;

    private function setupScenario(int $inventoryQty = 50, bool $assignWarehouse = true): array
    {
        $sales = User::factory()->sales()->create();
        $manager = User::factory()->warehouseManager()->create();
        $accountant = User::factory()->accountant()->create();

        $warehouse = Warehouse::factory()->create([
            'manager_id' => $manager->id,
            'sales_person_id' => $assignWarehouse ? $sales->id : null,
        ]);

        $this->productType = ProductType::factory()->create([
            'name' => 'Source Test Product',
            'available_grammages' => [100, 200],
        ]);

        Inventory::factory()->create([
            'warehouse_id' => $warehouse->id,
            'product_type_id' => $this->productType->id,
            'grammage' => 100,
            'quantity' => $inventoryQty,
        ]);

        return [$sales, $manager, $accountant, $warehouse];
    }

    private function orderAttributes(Customer $customer, User $sales): array
    {
        return [
            'customer_id' => $customer->id,
            'user_id' => $sales->id,
            'status' => OrderStatus::Pending,
            'total_price' => 1000.00,
            'is_migrated_order' => false,
        ];
    }

    private function addProduct(Order $order): void
    {
        $order->products()->create([
            'product_type_id' => $this->productType->id,
            'product_name' => $this->productType->name,
            'grammage' => 100,
            'quantity' => 4,
            'price' => 250,
        ]);
    }

    public function test_warehouse_order_links_transfer_and_delivers_end_to_end(): void
    {
        [$sales, $manager, $accountant, $warehouse] = $this->setupScenario();
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

        $order = Order::where('customer_id', $customer->id)->firstOrFail();
        $this->assertSame('warehouse', $order->stock_source);

        $transfer = StockTransfer::where('source_type', 'sales_order')->firstOrFail();
        $this->assertSame($order->id, $transfer->order_id);

        $this->actingAs($manager);
        StockTransferService::dispatchByWarehouse($transfer->fresh(), $manager->id);

        $this->actingAs($sales);
        OrderAssignmentService::attachPaymentProof($order, 'receipts/payment-proofs/p.png', $sales->id);
        $this->assertTrue(OrderAssignmentService::hasSufficientStock($sales, $order->fresh()));
        OrderAssignmentService::markOrderDelivered($order->fresh(), $sales, $sales->id);

        $this->assertSame(OrderStatus::Delivered, $order->fresh()->status);
    }

    public function test_office_sale_from_warehouse_approves_and_allocates(): void
    {
        [$sales, $manager, $accountant, $warehouse] = $this->setupScenario();

        $this->actingAs($sales);

        $record = SalesRecordService::submitSale([
            'agent_type' => 'sales',
            'stock_source' => 'warehouse',
            'warehouse_id' => $warehouse->id,
            'products' => [[
                'product_name' => $this->productType->name,
                'grammage' => 100,
                'quantity' => 10,
                'price' => 500,
            ]],
            'total_value' => 5000,
        ], $sales->id);

        $this->actingAs($accountant);
        SalesRecordService::approve($record->fresh(), [], $accountant->id);

        $this->assertSame('approved', $record->fresh()->status);
        $this->assertDatabaseHas('agent_stocks', [
            'user_id' => $sales->id,
            'product_name' => $this->productType->name,
            'grammage' => 100,
            'quantity' => 10,
        ]);
        $this->assertSame(40, Inventory::where('warehouse_id', $warehouse->id)->first()->quantity);
    }

    public function test_sales_without_assigned_warehouse_can_still_choose_active_warehouse(): void
    {
        [$sales, $manager, $accountant, $warehouse] = $this->setupScenario(assignWarehouse: false);
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

        $this->assertDatabaseHas('orders', [
            'customer_id' => $customer->id,
            'stock_source' => 'warehouse',
            'warehouse_id' => $warehouse->id,
        ]);
    }

    public function test_pending_widget_can_route_held_order_to_warehouse(): void
    {
        [$sales, $manager, $accountant, $warehouse] = $this->setupScenario();
        $customer = Customer::factory()->create();

        $order = Order::factory()->create(array_merge(
            $this->orderAttributes($customer, $sales),
            ['stock_source' => 'held']
        ));
        $this->addProduct($order);

        $this->actingAs($sales);

        Livewire::test(SalesPendingOrdersWidget::class)
            ->callTableAction('fulfillFromWarehouse', $order->id, ['warehouse_id' => $warehouse->id])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'stock_source' => 'warehouse',
            'warehouse_id' => $warehouse->id,
        ]);

        $this->assertDatabaseHas('stock_transfers', [
            'order_id' => $order->id,
            'from_warehouse_id' => $warehouse->id,
            'to_agent_id' => $sales->id,
            'status' => 'requested',
            'source_type' => 'sales_order',
        ]);
    }

    public function test_pending_widget_blocks_warehouse_routing_when_stock_short(): void
    {
        [$sales, $manager, $accountant, $warehouse] = $this->setupScenario(inventoryQty: 1);
        $customer = Customer::factory()->create();

        $order = Order::factory()->create(array_merge(
            $this->orderAttributes($customer, $sales),
            ['stock_source' => 'held']
        ));
        $this->addProduct($order);

        $this->actingAs($sales);

        Livewire::test(SalesPendingOrdersWidget::class)
            ->callTableAction('fulfillFromWarehouse', $order->id, ['warehouse_id' => $warehouse->id])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseMissing('stock_transfers', ['order_id' => $order->id]);
        $this->assertSame('held', $order->fresh()->stock_source);
    }

    public function test_pending_widget_shows_source_and_dispatch_columns(): void
    {
        [$sales, $manager, $accountant, $warehouse] = $this->setupScenario();
        $customer = Customer::factory()->create();

        $order = Order::factory()->create(array_merge(
            $this->orderAttributes($customer, $sales),
            ['stock_source' => 'warehouse', 'warehouse_id' => $warehouse->id]
        ));
        $this->addProduct($order);

        OrderAssignmentService::routeOrderToWarehouse($order, $warehouse->id, $sales->id, $sales->id);

        $this->actingAs($sales);

        Livewire::test(SalesPendingOrdersWidget::class)
            ->assertSee($customer->customer_name)
            ->assertSee('Warehouse')
            ->assertSee('Requested');
    }
}
