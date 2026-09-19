<?php

namespace Tests\Feature\Dashboards;

use App\Enums\OrderStatus;
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
use App\Livewire\CsrOrderBreakdownTable;
use App\Livewire\RevenueBreakdownTable;
use App\Models\Customer;
use App\Models\DamagedStockReturn;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\ProductType;
use App\Models\SalesRecord;
use App\Models\StockCount;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;
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

    public function test_revenue_stat_has_export_button(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $this->actingAs($supervisor);

        Livewire::test(SupervisorStatsWidget::class)
            ->assertSee('open-period-sales-export', escape: false)
            ->assertSee('Export');
    }

    public function test_open_period_sales_export_returns_csv(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $this->actingAs($supervisor);

        $csr = User::factory()->communitySalesRepresentative()->create();
        SalesRecord::factory()->create([
            'agent_id' => $csr->id,
            'agent_type' => 'community_sales_representative',
        ]);

        Livewire::test(SupervisorDashboard::class)
            ->call('exportPeriodSales')
            ->assertFileDownloaded();
    }

    public function test_revenue_breakdown_exports_summary_and_drilldown(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $this->actingAs($supervisor);

        $csr = User::factory()->communitySalesRepresentative()->create(['name' => 'Export Agent']);
        SalesRecord::factory()->approved()->create([
            'agent_id' => $csr->id,
            'agent_type' => 'community_sales_representative',
            'is_credit' => false,
            'total_value' => 5000,
            'customer_name' => 'Export Customer',
        ]);

        Livewire::test(RevenueBreakdownTable::class)
            ->call('exportCsv')
            ->assertFileDownloaded();

        Livewire::test(RevenueBreakdownTable::class)
            ->call('selectAgent', $csr->id)
            ->call('exportCsv')
            ->assertFileDownloaded();
    }

    public function test_csr_completed_orders_breakdown_shows_name_count_and_value(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $this->actingAs($supervisor);

        session()->put('supervisor_date_from', now()->startOfDay()->toDateTimeString());
        session()->put('supervisor_date_to', now()->endOfDay()->toDateTimeString());

        $csr = User::factory()->communitySalesRepresentative()->create(['name' => 'Top CSR']);
        $sales = User::factory()->sales()->create();
        $customer = Customer::factory()->create();

        $this->createCsrOrder($sales, $csr, $customer, ['total_price' => 4000]);
        $this->createCsrOrder($sales, $csr, $customer, ['total_price' => 6000]);

        Livewire::test(CsrOrderBreakdownTable::class)
            ->assertSee('Top CSR')
            ->assertSee('>2<', false)
            ->assertSee('₦10,000.00');
    }

    public function test_csr_completed_orders_breakdown_respects_supervisor_period(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $this->actingAs($supervisor);

        session()->put('supervisor_date_from', now()->startOfDay()->toDateTimeString());
        session()->put('supervisor_date_to', now()->endOfDay()->toDateTimeString());

        $csr = User::factory()->communitySalesRepresentative()->create(['name' => 'Period CSR']);
        $sales = User::factory()->sales()->create();
        $customer = Customer::factory()->create();

        $this->createCsrOrder($sales, $csr, $customer, ['total_price' => 5000, 'created_at' => now()]);
        $this->createCsrOrder($sales, $csr, $customer, ['total_price' => 9000, 'created_at' => now()->subDays(30)]);

        Livewire::test(CsrOrderBreakdownTable::class)
            ->assertSee('Period CSR')
            ->assertSee('>1<', false)
            ->assertSee('₦5,000.00')
            ->assertDontSee('₦9,000.00');
    }

    public function test_csr_completed_orders_breakdown_exports_summary_and_detail(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $this->actingAs($supervisor);

        $csr = User::factory()->communitySalesRepresentative()->create(['name' => 'Export CSR']);
        $sales = User::factory()->sales()->create();
        $customer = Customer::factory()->create();

        $order = $this->createCsrOrder($sales, $csr, $customer, ['total_price' => 7500]);

        Livewire::test(CsrOrderBreakdownTable::class)
            ->call('exportCsv')
            ->assertFileDownloaded();

        Livewire::test(CsrOrderBreakdownTable::class)
            ->call('selectCsr', $csr->id)
            ->assertSee('>#'.$order->id.'<', false)
            ->assertSee('₦7,500.00')
            ->call('exportCsv')
            ->assertFileDownloaded();
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

    public function test_supervisor_csr_overview_shows_order_and_credit_aggregates(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $this->actingAs($supervisor);

        $csr = User::factory()->communitySalesRepresentative()->create();
        $otherCsr = User::factory()->communitySalesRepresentative()->create();
        $customer = Customer::factory()->create();

        Session::put('supervisor_date_from', now()->startOfDay()->toDateTimeString());
        Session::put('supervisor_date_to', now()->endOfDay()->toDateTimeString());

        $this->createCsrOrder($supervisor, $csr, $customer, ['total_price' => 1000.00]);
        $this->createCsrOrder($supervisor, $csr, $customer, ['total_price' => 2500.00]);
        $this->createCsrOrder($supervisor, $csr, $customer, [
            'total_price' => 9000.00,
            'created_at' => now()->subDays(3),
        ]);
        $this->createCsrOrder($supervisor, $otherCsr, $customer, ['total_price' => 5000.00]);
        $this->createCsrOrder($supervisor, $csr, $customer, [
            'status' => 'pending',
            'total_price' => 1500.00,
        ]);
        $this->createCsrOrder($supervisor, $csr, $customer, [
            'status' => OrderStatus::Cancelled,
            'total_price' => 2000.00,
        ]);

        SalesRecord::factory()->approved()->create([
            'agent_id' => $csr->id,
            'agent_type' => 'community_sales_representative',
            'total_value' => 5000.00,
        ]);
        SalesRecord::factory()->credit()->create([
            'agent_id' => $csr->id,
            'agent_type' => 'community_sales_representative',
            'total_value' => 3000.00,
        ]);
        SalesRecord::factory()->collected()->create([
            'agent_id' => $csr->id,
            'agent_type' => 'community_sales_representative',
            'total_value' => 900.00,
        ]);
        SalesRecord::factory()->credit()->create([
            'agent_id' => $otherCsr->id,
            'agent_type' => 'community_sales_representative',
            'total_value' => 500.00,
        ]);

        Livewire::test(SupervisorCsrListWidget::class)
            ->assertTableColumnStateSet('completed_orders', 2, $csr)
            ->assertTableColumnStateSet('completed_value', 3500.0, $csr)
            ->assertTableColumnStateSet('pending_orders', 1, $csr)
            ->assertTableColumnStateSet('pending_value', 1500.0, $csr)
            ->assertTableColumnStateSet('credit_sales_value', 3000.0, $csr)
            ->assertTableColumnStateSet('sales_count', 3, $csr)
            ->assertTableColumnStateSet('sales_value', 8900.0, $csr)
            ->assertTableColumnStateSet('completed_orders', 1, $otherCsr)
            ->assertTableColumnStateSet('credit_sales_value', 500.0, $otherCsr)
            ->mountTableAction('view', $csr->id)
            ->assertMountedActionModalSee('Order Summary')
            ->assertMountedActionModalSee('Sales Summary')
            ->assertMountedActionModalSee('Pending Orders')
            ->assertMountedActionModalSee('Credit Sales Value');
    }

    public function test_supervisor_csr_overview_export_streams_xlsx_with_full_details(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $this->actingAs($supervisor);

        $csr = User::factory()->communitySalesRepresentative()->create();
        $customer = Customer::factory()->create();

        Session::put('supervisor_date_from', now()->startOfDay()->toDateTimeString());
        Session::put('supervisor_date_to', now()->endOfDay()->toDateTimeString());

        $this->createCsrOrder($supervisor, $csr, $customer, ['total_price' => 2500.00]);
        $this->createCsrOrder($supervisor, $csr, $customer, [
            'status' => 'pending',
            'total_price' => 1500.00,
        ]);
        SalesRecord::factory()->approved()->create([
            'agent_id' => $csr->id,
            'agent_type' => 'community_sales_representative',
            'total_value' => 5000.00,
        ]);
        SalesRecord::factory()->credit()->create([
            'agent_id' => $csr->id,
            'agent_type' => 'community_sales_representative',
            'total_value' => 3000.00,
        ]);

        $widget = Livewire::test(SupervisorCsrListWidget::class)->instance();
        $response = $widget->exportXlsx();

        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('Content-Type'),
        );
        $this->assertStringContainsString('.xlsx', $response->headers->get('Content-Disposition'));

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $path = tempnam(sys_get_temp_dir(), 'export_').'.xlsx';
        file_put_contents($path, $content);

        $reader = new Reader;
        $reader->open($path);

        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = array_map(fn ($cell) => $cell->getValue(), $row->getCells());
            }
        }

        $reader->close();
        unlink($path);

        $this->assertSame([
            'CSR Name', 'LGA', 'State', 'Status', 'Stock Units',
            'Sales Count', 'Sales Value', 'Credit Sales Value',
            'Completed Orders', 'Completed Value', 'Pending Orders', 'Pending Value',
        ], $rows[0]);

        $this->assertEquals([
            $csr->name,
            'N/A',
            'N/A',
            'Active',
            0,
            2,
            8000.0,
            3000.0,
            1,
            2500.0,
            1,
            1500.0,
        ], $rows[1] ?? []);
    }

    private function createCsrOrder(User $submitter, User $csr, Customer $customer, array $attributes = []): Order
    {
        return Order::factory()->create(array_merge([
            'customer_id' => $customer->id,
            'user_id' => $submitter->id,
            'assigned_to' => $csr->id,
            'status' => OrderStatus::Delivered,
            'total_price' => 1000.00,
            'is_migrated_order' => false,
        ], $attributes));
    }
}
