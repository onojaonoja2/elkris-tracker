<?php

namespace Tests\Feature\Dashboards;

use App\Enums\StockTransferStatus;
use App\Filament\Pages\WarehouseManagerDashboard;
use App\Filament\Widgets\WarehouseOutgoingDispatchesWidget;
use App\Models\Inventory;
use App\Models\ProductType;
use App\Models\Setting;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WarehouseManagerDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function warehouseManager(): User
    {
        return User::factory()->warehouseManager()->create();
    }

    private function managedWarehouse(User $manager): Warehouse
    {
        return Warehouse::factory()->create(['manager_id' => $manager->id]);
    }

    public function test_warehouse_manager_dashboard_renders(): void
    {
        $manager = $this->warehouseManager();
        $this->managedWarehouse($manager);

        $this->actingAs($manager)
            ->get('/admin/warehouse-dashboard')
            ->assertOk();
    }

    public function test_warehouse_damaged_stock_page_renders(): void
    {
        $manager = $this->warehouseManager();
        $this->managedWarehouse($manager);

        $this->actingAs($manager)
            ->get('/admin/warehouse-damaged-stock')
            ->assertOk();
    }

    public function test_outgoing_dispatches_widget_shows_only_unconfirmed_outbound_dispatches(): void
    {
        $manager = $this->warehouseManager();
        $warehouse = $this->managedWarehouse($manager);
        $destination = Warehouse::factory()->create();
        $agent = User::factory()->communitySalesRepresentative()->create();

        $dispatched = StockTransfer::create([
            'from_warehouse_id' => $warehouse->id,
            'to_warehouse_id' => $destination->id,
            'dispatched_by' => $manager->id,
            'status' => StockTransferStatus::Dispatched,
        ]);

        $received = StockTransfer::create([
            'from_warehouse_id' => $warehouse->id,
            'to_agent_id' => $agent->id,
            'dispatched_by' => $manager->id,
            'status' => StockTransferStatus::Received,
            'received_at' => now(),
        ]);

        $incoming = StockTransfer::create([
            'from_warehouse_id' => $destination->id,
            'to_warehouse_id' => $warehouse->id,
            'dispatched_by' => $manager->id,
            'status' => StockTransferStatus::Dispatched,
        ]);

        $this->actingAs($manager);

        Livewire::test(WarehouseOutgoingDispatchesWidget::class)
            ->assertCanSeeTableRecords([$dispatched])
            ->assertCanNotSeeTableRecords([$received, $incoming]);
    }

    public function test_outgoing_dispatches_widget_respects_dashboard_date_filter(): void
    {
        $manager = $this->warehouseManager();
        $warehouse = $this->managedWarehouse($manager);

        $today = StockTransfer::create([
            'from_warehouse_id' => $warehouse->id,
            'dispatched_by' => $manager->id,
            'status' => StockTransferStatus::Dispatched,
        ]);

        $old = StockTransfer::create([
            'from_warehouse_id' => $warehouse->id,
            'dispatched_by' => $manager->id,
            'status' => StockTransferStatus::Dispatched,
        ]);
        $old->created_at = now()->subDays(7);
        $old->save();

        $this->actingAs($manager);

        session()->put('dashboard_date_from', now()->startOfDay()->toDateTimeString());
        session()->put('dashboard_date_to', now()->endOfDay()->toDateTimeString());

        Livewire::test(WarehouseOutgoingDispatchesWidget::class)
            ->assertCanSeeTableRecords([$today])
            ->assertCanNotSeeTableRecords([$old]);
    }

    public function test_export_query_scopes_outgoing_dispatches_to_filter_and_warehouse(): void
    {
        $manager = $this->warehouseManager();
        $warehouse = $this->managedWarehouse($manager);
        $otherWarehouse = Warehouse::factory()->create();

        StockTransfer::create([
            'from_warehouse_id' => $warehouse->id,
            'dispatched_by' => $manager->id,
            'status' => StockTransferStatus::Dispatched,
            'created_at' => now(),
        ]);

        StockTransfer::create([
            'from_warehouse_id' => $warehouse->id,
            'dispatched_by' => $manager->id,
            'status' => StockTransferStatus::Received,
            'received_at' => now(),
            'created_at' => now(),
        ]);

        StockTransfer::create([
            'from_warehouse_id' => $otherWarehouse->id,
            'dispatched_by' => $manager->id,
            'status' => StockTransferStatus::Dispatched,
            'created_at' => now(),
        ]);

        $this->actingAs($manager);

        session()->put('dashboard_date_from', now()->startOfDay()->toDateTimeString());
        session()->put('dashboard_date_to', now()->endOfDay()->toDateTimeString());

        $records = StockTransfer::whereIn('from_warehouse_id', [$warehouse->id])
            ->where('status', StockTransferStatus::Dispatched)
            ->whereBetween('created_at', [
                now()->startOfDay(),
                now()->endOfDay(),
            ])
            ->get();

        $this->assertCount(1, $records);
        $this->assertSame($warehouse->id, $records->first()->from_warehouse_id);
        $this->assertSame(StockTransferStatus::Dispatched, $records->first()->status);
    }

    private function dispatchStockSetup(User $manager): array
    {
        $warehouse = $this->managedWarehouse($manager);
        $productType = ProductType::factory()->create(['available_grammages' => [100, 200, 500]]);

        Inventory::create([
            'warehouse_id' => $warehouse->id,
            'product_type_id' => $productType->id,
            'grammage' => 100,
            'quantity' => 50,
        ]);

        return [$warehouse, $productType];
    }

    private function mockDispatchPdf(): void
    {
        Pdf::shouldReceive('loadView')->andReturnSelf();
        Pdf::shouldReceive('save')->once();
    }

    private function fillDispatchForm(
        mixed $component,
        Warehouse $warehouse,
        ProductType $productType,
        string $toType,
        ?int $agentId,
    ): mixed {
        return $component
            ->set('mountedActions.0.data.from_warehouse_id', $warehouse->id)
            ->set('mountedActions.0.data.to_type', $toType)
            ->set('mountedActions.0.data.to_agent_id', $agentId)
            ->set('mountedActions.0.data.items', [[
                'product_type_id' => $productType->id,
                'grammage' => '100',
                'quantity' => 5,
            ]]);
    }

    public function test_warehouse_manager_can_dispatch_to_open_market_agent(): void
    {
        $manager = $this->warehouseManager();
        [$warehouse, $productType] = $this->dispatchStockSetup($manager);
        $agent = User::factory()->state(['role' => 'open_market'])->create();
        $this->mockDispatchPdf();

        $this->actingAs($manager);

        $this->fillDispatchForm(
            Livewire::test(WarehouseManagerDashboard::class)->mountAction('dispatchStock'),
            $warehouse,
            $productType,
            'agent',
            $agent->id,
        )
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('stock_transfers', [
            'from_warehouse_id' => $warehouse->id,
            'to_agent_id' => $agent->id,
            'status' => StockTransferStatus::Dispatched,
        ]);
    }

    public function test_warehouse_manager_can_dispatch_to_retail_market_agent(): void
    {
        $manager = $this->warehouseManager();
        [$warehouse, $productType] = $this->dispatchStockSetup($manager);
        $agent = User::factory()->state(['role' => 'retail_market'])->create();
        $this->mockDispatchPdf();

        $this->actingAs($manager);

        $this->fillDispatchForm(
            Livewire::test(WarehouseManagerDashboard::class)->mountAction('dispatchStock'),
            $warehouse,
            $productType,
            'agent',
            $agent->id,
        )
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('stock_transfers', [
            'from_warehouse_id' => $warehouse->id,
            'to_agent_id' => $agent->id,
            'status' => StockTransferStatus::Dispatched,
        ]);
    }

    public function test_warehouse_manager_can_dispatch_to_sales_person(): void
    {
        $manager = $this->warehouseManager();
        [$warehouse, $productType] = $this->dispatchStockSetup($manager);
        $agent = User::factory()->sales()->create();
        $this->mockDispatchPdf();

        $this->actingAs($manager);

        $this->fillDispatchForm(
            Livewire::test(WarehouseManagerDashboard::class)->mountAction('dispatchStock'),
            $warehouse,
            $productType,
            'agent',
            $agent->id,
        )
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('stock_transfers', [
            'from_warehouse_id' => $warehouse->id,
            'to_agent_id' => $agent->id,
            'status' => StockTransferStatus::Dispatched,
        ]);
    }

    public function test_warehouse_manager_can_dispatch_to_community_sales_representative(): void
    {
        $manager = $this->warehouseManager();
        [$warehouse, $productType] = $this->dispatchStockSetup($manager);
        $csr = User::factory()->communitySalesRepresentative()->create();
        $this->mockDispatchPdf();

        $this->actingAs($manager);

        $this->fillDispatchForm(
            Livewire::test(WarehouseManagerDashboard::class)->mountAction('dispatchStock'),
            $warehouse,
            $productType,
            'community_sales_representative',
            $csr->id,
        )
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('stock_transfers', [
            'from_warehouse_id' => $warehouse->id,
            'to_agent_id' => $csr->id,
            'status' => StockTransferStatus::Dispatched,
        ]);
    }

    public function test_agent_dispatch_requires_to_agent_id(): void
    {
        $manager = $this->warehouseManager();
        [$warehouse, $productType] = $this->dispatchStockSetup($manager);

        $this->actingAs($manager);

        $this->fillDispatchForm(
            Livewire::test(WarehouseManagerDashboard::class)->mountAction('dispatchStock'),
            $warehouse,
            $productType,
            'agent',
            null,
        )
            ->callMountedAction()
            ->assertHasActionErrors(['to_agent_id']);
    }

    public function test_warehouse_manager_submits_regular_stock_count_as_pending(): void
    {
        Setting::setValue('stock_at_hand_enabled', '1');

        $manager = $this->warehouseManager();
        [$warehouse, $productType] = $this->dispatchStockSetup($manager);

        $this->actingAs($manager);

        Livewire::test(WarehouseManagerDashboard::class)
            ->mountAction('submitStockCount')
            ->set('mountedActions.0.data.items', [[
                'product_type_id' => $productType->id,
                'grammage' => '100',
                'quantity' => 10,
            ]])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('stock_counts', [
            'user_id' => $manager->id,
            'warehouse_id' => $warehouse->id,
            'is_additional_count' => 0,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('inventories', [
            'warehouse_id' => $warehouse->id,
            'product_type_id' => $productType->id,
            'grammage' => 100,
            'quantity' => 50,
        ]);

        $this->assertDatabaseCount('stock_transactions', 0);
    }

    public function test_warehouse_manager_submits_additional_stock_count_that_adds_to_inventory(): void
    {
        Setting::setValue('stock_at_hand_enabled', '1');

        $manager = $this->warehouseManager();
        [$warehouse, $productType] = $this->dispatchStockSetup($manager);

        $this->actingAs($manager);

        Livewire::test(WarehouseManagerDashboard::class)
            ->mountAction('submitStockCount')
            ->set('mountedActions.0.data.is_additional_count', true)
            ->set('mountedActions.0.data.items', [[
                'product_type_id' => $productType->id,
                'grammage' => '100',
                'quantity' => 10,
            ]])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('stock_counts', [
            'user_id' => $manager->id,
            'warehouse_id' => $warehouse->id,
            'is_additional_count' => 1,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('inventories', [
            'warehouse_id' => $warehouse->id,
            'product_type_id' => $productType->id,
            'grammage' => 100,
            'quantity' => 60,
        ]);

        $this->assertDatabaseHas('stock_transactions', [
            'type' => 'received',
            'product_type_id' => $productType->id,
            'quantity' => 10,
            'warehouse_id' => $warehouse->id,
        ]);
    }
}
