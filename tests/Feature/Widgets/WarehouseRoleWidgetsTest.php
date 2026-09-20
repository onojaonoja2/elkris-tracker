<?php

namespace Tests\Feature\Widgets;

use App\Enums\OrderStatus;
use App\Filament\Widgets\WarehouseCsrStockWidget;
use App\Filament\Widgets\WarehouseOutgoingDispatchesWidget;
use App\Filament\Widgets\WarehouseRecentMovementsWidget;
use App\Filament\Widgets\WarehouseStocksWidget;
use App\Models\AgentStock;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\ProductType;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use Tests\TestCase;

class WarehouseRoleWidgetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_warehouse_manager_sees_csr_stock_summary(): void
    {
        $manager = User::factory()->warehouseManager()->create();
        $csr = User::factory()->communitySalesRepresentative()->create(['name' => 'Mina CSR']);
        $productType = ProductType::factory()->create(['name' => 'Ora herbal mix']);

        AgentStock::factory()->create([
            'user_id' => $csr->id,
            'product_type_id' => $productType->id,
            'product_name' => $productType->name,
            'grammage' => 100,
            'quantity' => 30,
        ]);
        AgentStock::factory()->create([
            'user_id' => $csr->id,
            'product_type_id' => $productType->id,
            'product_name' => $productType->name,
            'grammage' => 500,
            'quantity' => 20,
        ]);

        $this->actingAs($manager);

        Livewire::test(WarehouseCsrStockWidget::class)
            ->assertCanSeeTableRecords([$csr])
            ->assertTableColumnStateSet('stock_units', 50, $csr)
            ->assertTableColumnStateSet('product_lines', 2, $csr)
            ->assertTableActionExists('viewBreakdown')
            ->assertTableActionExists('exportCsrStock');
    }

    public function test_warehouse_manager_can_open_csr_stock_breakdown_modal(): void
    {
        $manager = User::factory()->warehouseManager()->create();
        $csr = User::factory()->communitySalesRepresentative()->create(['name' => 'Mina CSR']);
        $productType = ProductType::factory()->create(['name' => 'Ora herbal mix']);

        AgentStock::factory()->create([
            'user_id' => $csr->id,
            'product_type_id' => $productType->id,
            'product_name' => $productType->name,
            'grammage' => 100,
            'quantity' => 30,
        ]);

        $this->actingAs($manager);

        Livewire::test(WarehouseCsrStockWidget::class)
            ->callTableAction('viewBreakdown', $csr->id)
            ->assertHasNoTableActionErrors();
    }

    public function test_warehouse_manager_sees_csr_completed_orders_scoped_by_dashboard_date(): void
    {
        $manager = User::factory()->warehouseManager()->create();
        $csr = User::factory()->communitySalesRepresentative()->create(['name' => 'Mina CSR']);

        Session::put('dashboard_date_from', now()->startOfDay()->toDateTimeString());
        Session::put('dashboard_date_to', now()->endOfDay()->toDateTimeString());

        $this->order($csr, OrderStatus::Delivered, 1000, now());
        $this->order($csr, OrderStatus::Delivered, 2500, now());
        $this->order($csr, OrderStatus::Delivered, 3000, now()->subDay());
        $this->order($csr, OrderStatus::Pending, 500, now());
        $this->order($csr, OrderStatus::Delivered, 900, now(), isMigrated: true);

        $this->actingAs($manager);

        Livewire::test(WarehouseCsrStockWidget::class)
            ->assertTableColumnStateSet('completed_orders', 2, $csr)
            ->assertTableColumnStateSet('completed_value', 3500, $csr);
    }

    public function test_warehouse_manager_csr_modal_shows_order_summary_for_selected_period(): void
    {
        $manager = User::factory()->warehouseManager()->create();
        $csr = User::factory()->communitySalesRepresentative()->create(['name' => 'Mina CSR']);

        Session::put('dashboard_date_from', now()->startOfDay()->toDateTimeString());
        Session::put('dashboard_date_to', now()->endOfDay()->toDateTimeString());

        $this->order($csr, OrderStatus::Delivered, 1000, now());
        $this->order($csr, OrderStatus::Pending, 500, now());
        $this->order($csr, OrderStatus::Cancelled, 700, now());

        $this->actingAs($manager);

        $periodLabel = now()->format('d M Y').' - '.now()->format('d M Y');

        Livewire::test(WarehouseCsrStockWidget::class)
            ->mountTableAction('viewBreakdown', $csr->id)
            ->assertHasNoActionErrors()
            ->assertMountedActionModalSee('Completed Orders')
            ->assertMountedActionModalSee('Pending Orders Assigned')
            ->assertMountedActionModalSee('Completed Value')
            ->assertMountedActionModalSee($periodLabel);
    }

    private function order(User $csr, OrderStatus $status, int $totalPrice, $createdAt, bool $isMigrated = false): Order
    {
        return Order::factory()->create([
            'customer_id' => Customer::factory()->create()->id,
            'user_id' => User::factory()->rep()->create()->id,
            'assigned_to' => $csr->id,
            'status' => $status,
            'total_price' => $totalPrice,
            'is_migrated_order' => $isMigrated,
            'created_at' => $createdAt,
        ]);
    }

    public function test_warehouse_manager_sees_stock_from_all_warehouses(): void
    {
        $manager = User::factory()->warehouseManager()->create();
        Warehouse::factory()->create(['name' => 'Managed Warehouse']);
        $otherWarehouse = Warehouse::factory()->create(['name' => 'Remote Warehouse']);

        $productType = ProductType::factory()->create(['name' => 'Ora herbal mix']);
        $productType->update(['available_grammages' => [['grammage' => 100, 'carton_quantity' => 10]]]);

        $remote = Inventory::create([
            'warehouse_id' => $otherWarehouse->id,
            'product_type_id' => $productType->id,
            'grammage' => 100,
            'quantity' => 25,
        ]);

        $this->actingAs($manager);

        Livewire::test(WarehouseStocksWidget::class)
            ->assertCanSeeTableRecords([$remote])
            ->assertTableColumnStateSet('full_cartons', 2, $remote)
            ->assertTableColumnStateSet('remaining_pieces', 5, $remote)
            ->assertTableActionExists('viewMovements');
    }

    public function test_warehouse_manager_dispatch_opens_stock_movement_breakdown(): void
    {
        $manager = User::factory()->warehouseManager()->create();
        $warehouse = Warehouse::factory()->create(['name' => 'Remote Warehouse']);

        $productType = ProductType::factory()->create(['name' => 'Ora herbal mix']);
        $inventory = Inventory::create([
            'warehouse_id' => $warehouse->id,
            'product_type_id' => $productType->id,
            'grammage' => 100,
            'quantity' => 5,
        ]);

        $this->actingAs($manager);

        Livewire::test(WarehouseStocksWidget::class)
            ->callTableAction('viewMovements', $inventory->id)
            ->assertDispatched('open-stock-movement-breakdown');
    }

    public function test_recent_movements_widget_has_view_action_with_modal(): void
    {
        $manager = User::factory()->warehouseManager()->create();
        $warehouse = Warehouse::factory()->create(['name' => 'Main Hub', 'manager_id' => $manager->id]);
        $toWarehouse = Warehouse::factory()->create(['name' => 'Remote Hub']);
        $transfer = StockTransfer::factory()->create([
            'from_warehouse_id' => $warehouse->id,
            'to_warehouse_id' => $toWarehouse->id,
            'status' => 'dispatched',
        ]);

        $this->actingAs($manager);

        Livewire::test(WarehouseRecentMovementsWidget::class)
            ->assertCanSeeTableRecords([$transfer])
            ->assertTableActionExists('view')
            ->mountTableAction('view', $transfer->id)
            ->assertHasNoActionErrors()
            ->assertMountedActionModalSee('Main Hub')
            ->assertMountedActionModalSee('Remote Hub');
    }

    public function test_outgoing_dispatches_widget_has_view_action_with_modal(): void
    {
        $manager = User::factory()->warehouseManager()->create();
        $warehouse = Warehouse::factory()->create(['name' => 'Main Hub', 'manager_id' => $manager->id]);
        $toWarehouse = Warehouse::factory()->create(['name' => 'Remote Hub']);
        $agent = User::factory()->communitySalesRepresentative()->create(['name' => 'Jude Agent']);
        $transfer = StockTransfer::factory()->create([
            'from_warehouse_id' => $warehouse->id,
            'to_warehouse_id' => $toWarehouse->id,
            'to_agent_id' => $agent->id,
            'dispatched_by' => $manager->id,
            'status' => 'dispatched',
        ]);

        $this->actingAs($manager);

        Livewire::test(WarehouseOutgoingDispatchesWidget::class)
            ->assertCanSeeTableRecords([$transfer])
            ->assertTableActionExists('view')
            ->mountTableAction('view', $transfer->id)
            ->assertHasNoActionErrors()
            ->assertMountedActionModalSee('Main Hub')
            ->assertMountedActionModalSee('Remote Hub')
            ->assertMountedActionModalSee('Jude Agent');
    }
}
