<?php

namespace Tests\Feature\Dashboards;

use App\Enums\StockTransferStatus;
use App\Filament\Pages\SupervisorDashboard;
use App\Filament\Widgets\AccountantStockReceiveRequestsWidget;
use App\Filament\Widgets\SupervisorCreditSalesWidget;
use App\Filament\Widgets\SupervisorCsrListWidget;
use App\Filament\Widgets\SupervisorDamagedReturnsWidget;
use App\Filament\Widgets\SupervisorDispatchStockWidget;
use App\Filament\Widgets\SupervisorSalesRecordsWidget;
use App\Filament\Widgets\SupervisorStatsWidget;
use App\Filament\Widgets\SupervisorStockCountApprovalWidget;
use App\Filament\Widgets\SupervisorStockTransferApprovalWidget;
use App\Livewire\RevenueBreakdownTable;
use App\Models\DamagedStockReturn;
use App\Models\Inventory;
use App\Models\ProductType;
use App\Models\SalesRecord;
use App\Models\StockCount;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SupervisorDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_dashboard_renders(): void
    {
        $supervisor = User::factory()->supervisor()->create();

        $this->actingAs($supervisor)
            ->get('/admin/supervisor-dashboard')
            ->assertOk();
    }

    public function test_revenue_stat_dispatches_open_revenue_breakdown_event(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $this->actingAs($supervisor);

        Livewire::test(SupervisorStatsWidget::class)
            ->assertSee("dispatch('open-revenue-breakdown')", escape: false)
            ->assertSee('Revenue');
    }

    public function test_open_revenue_breakdown_mounts_modal(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $this->actingAs($supervisor);

        Livewire::test(SupervisorDashboard::class)
            ->call('openRevenueBreakdown')
            ->assertSet('breakdownType', 'revenue')
            ->assertActionMounted('revenueBreakdown');
    }

    public function test_revenue_breakdown_table_lists_agents_and_drills_down(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $this->actingAs($supervisor);

        $csr = User::factory()->communitySalesRepresentative()->create(['name' => 'Revenue Agent']);
        $record = SalesRecord::factory()->approved()->create([
            'agent_id' => $csr->id,
            'agent_type' => 'community_sales_representative',
            'is_credit' => false,
            'total_value' => 2500,
            'customer_name' => 'Drilldown Customer',
        ]);

        Livewire::test(RevenueBreakdownTable::class)
            ->assertSee('Revenue Agent')
            ->assertSee('₦2,500.00');

        Livewire::test(RevenueBreakdownTable::class)
            ->call('selectAgent', $csr->id)
            ->assertSee('Revenue Agent')
            ->assertSee('Drilldown Customer');
    }

    public function test_supervisor_widgets_expose_view_actions(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $this->actingAs($supervisor);

        $warehouse = Warehouse::factory()->create();
        $csr = User::factory()->communitySalesRepresentative()->create();
        $productType = ProductType::factory()->create(['name' => fake()->unique()->word()]);

        $transfer = StockTransfer::create([
            'from_warehouse_id' => $warehouse->id,
            'to_agent_id' => $csr->id,
            'requested_by' => $csr->id,
            'status' => StockTransferStatus::Requested,
            'requires_approval' => true,
        ]);

        $stockCount = StockCount::create([
            'user_id' => $csr->id,
            'is_additional_count' => false,
            'status' => 'pending',
        ]);
        $stockCount->items()->create([
            'product_type_id' => $productType->id,
            'product_name' => $productType->name,
            'grammage' => 100,
            'quantity' => 5,
        ]);

        $damagedReturn = DamagedStockReturn::factory()->create([
            'user_id' => $csr->id,
            'status' => 'pending',
            'product_type_id' => $productType->id,
        ]);

        SalesRecord::factory()->create([
            'agent_id' => $csr->id,
            'agent_type' => 'community_sales_representative',
            'status' => 'pending',
        ]);

        Livewire::test(SupervisorStockTransferApprovalWidget::class)
            ->assertTableActionExists('view');

        Livewire::test(SupervisorStockCountApprovalWidget::class)
            ->assertTableActionExists('view');

        Livewire::test(SupervisorDamagedReturnsWidget::class)
            ->assertTableActionExists('view');

        Livewire::test(SupervisorSalesRecordsWidget::class)
            ->assertTableActionExists('view');

        Livewire::test(SupervisorCreditSalesWidget::class)
            ->assertTableActionExists('view');

        Livewire::test(SupervisorCsrListWidget::class)
            ->assertTableActionExists('view');
    }

    public function test_sales_record_view_action_handles_malformed_products(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $this->actingAs($supervisor);

        $csr = User::factory()->communitySalesRepresentative()->create();
        $record = SalesRecord::factory()->create([
            'agent_id' => $csr->id,
            'agent_type' => 'community_sales_representative',
            'status' => 'pending',
            'products' => [1, 2, 3],
        ]);

        Livewire::test(SupervisorSalesRecordsWidget::class)
            ->mountTableAction('view', $record->id)
            ->assertHasNoActionErrors();
    }

    public function test_supervisor_can_dispatch_stock_to_csr(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $this->actingAs($supervisor);

        $warehouse = Warehouse::factory()->create();
        $csr = User::factory()->communitySalesRepresentative()->create();
        $productType = ProductType::factory()->create(['name' => fake()->unique()->word()]);

        $inventory = Inventory::create([
            'warehouse_id' => $warehouse->id,
            'product_type_id' => $productType->id,
            'grammage' => 100,
            'quantity' => 50,
        ]);

        Livewire::test(SupervisorDispatchStockWidget::class)
            ->mountTableAction('dispatchStock')
            ->set('mountedActions.0.data.from_warehouse_id', $warehouse->id)
            ->set('mountedActions.0.data.to_agent_id', $csr->id)
            ->set('mountedActions.0.data.items', [[
                'product_type_id' => $productType->id,
                'grammage' => '100',
                'quantity' => 10,
            ]])
            ->set('mountedActions.0.data.notes', 'Top up for the week')
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('stock_transfers', [
            'from_warehouse_id' => $warehouse->id,
            'to_agent_id' => $csr->id,
            'requested_by' => $supervisor->id,
            'status' => StockTransferStatus::Requested,
            'source_type' => 'supervisor_dispatch',
            'requires_approval' => true,
            'notes' => 'Top up for the week',
        ]);

        $transfer = StockTransfer::where('source_type', 'supervisor_dispatch')->first();
        $this->assertNotNull($transfer);
        $this->assertDatabaseHas('stock_transfer_items', [
            'stock_transfer_id' => $transfer->id,
            'product_type_id' => $productType->id,
            'grammage' => 100,
            'quantity' => 10,
        ]);

        $this->assertSame(50, $inventory->fresh()->quantity);
    }

    public function test_accountant_verifies_supervisor_dispatch_moving_stock(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $accountant = User::factory()->accountant()->create();

        $warehouse = Warehouse::factory()->create();
        $csr = User::factory()->communitySalesRepresentative()->create();
        $productType = ProductType::factory()->create(['name' => fake()->unique()->word()]);

        Inventory::create([
            'warehouse_id' => $warehouse->id,
            'product_type_id' => $productType->id,
            'grammage' => 100,
            'quantity' => 50,
        ]);

        $this->actingAs($supervisor);

        Livewire::test(SupervisorDispatchStockWidget::class)
            ->mountTableAction('dispatchStock')
            ->set('mountedActions.0.data.from_warehouse_id', $warehouse->id)
            ->set('mountedActions.0.data.to_agent_id', $csr->id)
            ->set('mountedActions.0.data.items', [[
                'product_type_id' => $productType->id,
                'grammage' => '100',
                'quantity' => 15,
            ]])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $transfer = StockTransfer::where('source_type', 'supervisor_dispatch')->firstOrFail();

        $this->actingAs($accountant);

        Livewire::test(AccountantStockReceiveRequestsWidget::class)
            ->assertCanSeeTableRecords([$transfer])
            ->callTableAction('approveReceive', $transfer->id);

        $this->assertDatabaseHas('stock_transfers', [
            'id' => $transfer->id,
            'status' => StockTransferStatus::Received,
            'approved_by' => $accountant->id,
        ]);

        $this->assertDatabaseHas('agent_stocks', [
            'user_id' => $csr->id,
            'product_type_id' => $productType->id,
            'grammage' => 100,
            'quantity' => 15,
        ]);

        $this->assertDatabaseHas('inventories', [
            'warehouse_id' => $warehouse->id,
            'product_type_id' => $productType->id,
            'grammage' => 100,
            'quantity' => 35,
        ]);
    }
}
