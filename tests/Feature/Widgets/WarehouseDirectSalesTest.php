<?php

namespace Tests\Feature\Widgets;

use App\Enums\OrderStatus;
use App\Filament\Pages\WarehouseManagerDashboard;
use App\Filament\Widgets\RepOrderAssignmentWidget;
use App\Filament\Widgets\WarehouseDirectSalesWidget;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\ProductType;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\NewSubmissionNotification;
use App\Services\OrderAssignmentService;
use App\Services\SalesRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class WarehouseDirectSalesTest extends TestCase
{
    use RefreshDatabase;

    private ProductType $productType;

    private function setupScenario(int $inventoryQty = 50): array
    {
        $rep = User::factory()->rep()->create();
        $manager = User::factory()->warehouseManager()->create();
        $accountant = User::factory()->accountant()->create();

        $warehouse = Warehouse::factory()->create(['manager_id' => $manager->id]);

        $this->productType = ProductType::factory()->create([
            'name' => 'Warehouse Sale Product',
            'available_grammages' => [100, 200],
        ]);

        Inventory::factory()->create([
            'warehouse_id' => $warehouse->id,
            'product_type_id' => $this->productType->id,
            'grammage' => 100,
            'quantity' => $inventoryQty,
        ]);

        return [$rep, $manager, $accountant, $warehouse];
    }

    private function createRepOrder(User $rep): Order
    {
        $order = Order::factory()->create([
            'customer_id' => Customer::factory()->create()->id,
            'user_id' => $rep->id,
            'status' => OrderStatus::Pending,
            'total_price' => 1000.00,
            'is_migrated_order' => false,
        ]);

        $order->products()->create([
            'product_type_id' => $this->productType->id,
            'product_name' => $this->productType->name,
            'grammage' => 100,
            'quantity' => 4,
            'price' => 250,
        ]);

        return $order;
    }

    public function test_rep_can_send_order_to_warehouse(): void
    {
        [$rep, $manager, $accountant, $warehouse] = $this->setupScenario();
        $order = $this->createRepOrder($rep);

        $this->actingAs($rep);

        Livewire::test(RepOrderAssignmentWidget::class)
            ->callTableAction('sendToWarehouse', $order->id, ['warehouse_id' => $warehouse->id])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'stock_source' => 'warehouse',
            'warehouse_id' => $warehouse->id,
        ]);

        $this->assertDatabaseHas('stock_transfers', [
            'order_id' => $order->id,
            'from_warehouse_id' => $warehouse->id,
            'to_agent_id' => $rep->id,
            'status' => 'requested',
            'source_type' => 'sales_order',
        ]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $manager->id,
            'type' => NewSubmissionNotification::class,
        ]);
    }

    public function test_rep_cannot_route_twice_or_without_stock(): void
    {
        [$rep, $manager, $accountant, $warehouse] = $this->setupScenario(inventoryQty: 1);
        $order = $this->createRepOrder($rep);

        $this->actingAs($rep);

        Livewire::test(RepOrderAssignmentWidget::class)
            ->callTableAction('sendToWarehouse', $order->id, ['warehouse_id' => $warehouse->id])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseMissing('stock_transfers', ['order_id' => $order->id]);

        // Sufficient stock now, route once, then attempt a duplicate.
        Inventory::where('warehouse_id', $warehouse->id)->increment('quantity', 50);

        Livewire::test(RepOrderAssignmentWidget::class)
            ->callTableAction('sendToWarehouse', $order->id, ['warehouse_id' => $warehouse->id])
            ->assertHasNoTableActionErrors();

        $this->expectException(ValidationException::class);
        OrderAssignmentService::routeOrderToWarehouse($order->fresh(), $warehouse->id, $rep->id, $rep->id);
    }

    public function test_warehouse_manager_records_direct_sale(): void
    {
        [$rep, $manager, $accountant, $warehouse] = $this->setupScenario();

        $this->actingAs($manager);

        Livewire::test(WarehouseManagerDashboard::class)
            ->mountAction('recordDirectSale')
            ->fillForm([
                'warehouse_id' => $warehouse->id,
                'customer_name' => 'Walk-in Buyer',
            ])
            ->set('mountedActions.0.data.items', [[
                'product_type_id' => $this->productType->id,
                'grammage' => '100',
                'quantity' => 5,
                'price' => 500,
            ]])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('sales_records', [
            'agent_id' => $manager->id,
            'agent_type' => 'warehouse_manager',
            'warehouse_id' => $warehouse->id,
            'stock_source' => 'warehouse',
            'status' => 'pending',
            'customer_name' => 'Walk-in Buyer',
        ]);

        $this->assertSame(45, Inventory::where('warehouse_id', $warehouse->id)->first()->quantity);

        Livewire::test(WarehouseDirectSalesWidget::class)
            ->assertSee('Walk-in Buyer');
    }

    public function test_direct_sale_approve_and_reject_restore_stock(): void
    {
        [$rep, $manager, $accountant, $warehouse] = $this->setupScenario();

        $record = SalesRecordService::submitWarehouseDirectSale([
            'warehouse_id' => $warehouse->id,
            'products' => [[
                'product_name' => $this->productType->name,
                'grammage' => 100,
                'quantity' => 5,
                'price' => 500,
            ]],
            'total_value' => 2500,
            'customer_name' => 'Approve Buyer',
        ], $manager->id);

        SalesRecordService::approve($record->fresh(), [], $accountant->id);
        $this->assertSame('approved', $record->fresh()->status);

        $rejected = SalesRecordService::submitWarehouseDirectSale([
            'warehouse_id' => $warehouse->id,
            'products' => [[
                'product_name' => $this->productType->name,
                'grammage' => 100,
                'quantity' => 5,
                'price' => 500,
            ]],
            'total_value' => 2500,
            'customer_name' => 'Reject Buyer',
        ], $manager->id);

        SalesRecordService::reject($rejected->fresh(), 'Customer cancelled', $accountant->id);

        $this->assertSame('rejected', $rejected->fresh()->status);
        // 50 - 5 (approved sale) - 5 (rejected sale) + 5 (restored) = 45.
        $this->assertSame(45, Inventory::where('warehouse_id', $warehouse->id)->first()->quantity);
    }

    public function test_direct_sale_blocked_for_unmanaged_warehouse_or_short_stock(): void
    {
        [$rep, $manager, $accountant, $warehouse] = $this->setupScenario();
        $otherWarehouse = Warehouse::factory()->create();

        $payload = [
            'warehouse_id' => $otherWarehouse->id,
            'products' => [[
                'product_name' => $this->productType->name,
                'grammage' => 100,
                'quantity' => 5,
                'price' => 500,
            ]],
            'total_value' => 2500,
        ];

        try {
            SalesRecordService::submitWarehouseDirectSale($payload, $manager->id);
            $this->fail('Expected ValidationException for unmanaged warehouse.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('warehouse_id', $e->errors());
        }

        try {
            SalesRecordService::submitWarehouseDirectSale([
                'warehouse_id' => $warehouse->id,
                'products' => [[
                    'product_name' => $this->productType->name,
                    'grammage' => 100,
                    'quantity' => 5000,
                    'price' => 500,
                ]],
                'total_value' => 2500000,
            ], $manager->id);
            $this->fail('Expected ValidationException for insufficient stock.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('products', $e->errors());
        }
    }
}
