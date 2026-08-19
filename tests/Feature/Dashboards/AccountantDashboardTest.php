<?php

namespace Tests\Feature\Dashboards;

use App\Enums\StockTransferStatus;
use App\Filament\Pages\AccountantDashboard;
use App\Filament\Widgets\AccountantCreditSalesWidget;
use App\Filament\Widgets\AccountantDamagedReturnsWidget;
use App\Filament\Widgets\AccountantRepSalesWidget;
use App\Filament\Widgets\AccountantSalesRecordsWidget;
use App\Filament\Widgets\AccountantStockCountApprovalWidget;
use App\Filament\Widgets\AccountantStockLevelsWidget;
use App\Filament\Widgets\AccountantStockMovementsWidget;
use App\Filament\Widgets\AccountantStockTransferApprovalWidget;
use App\Filament\Widgets\DamagedReturnsBreakdownWidget;
use App\Livewire\RepSalesBreakdownTable;
use App\Livewire\StockMovementBreakdownTable;
use App\Models\AgentStock;
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
use Livewire\Livewire;
use Tests\TestCase;

class AccountantDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_accountant_dashboard_renders(): void
    {
        $accountant = User::factory()->accountant()->create();

        $this->actingAs($accountant)
            ->get('/admin/accountant-dashboard')
            ->assertOk();
    }

    public function test_accountant_transaction_widgets_expose_view_actions(): void
    {
        $accountant = User::factory()->accountant()->create();
        $this->actingAs($accountant);

        $warehouse = Warehouse::factory()->create();
        $supervisor = User::factory()->supervisor()->create();
        $openMarket = User::factory()->openMarket()->create();
        $productType = ProductType::factory()->create(['name' => fake()->unique()->word()]);

        $pendingTransfer = StockTransfer::create([
            'from_warehouse_id' => $warehouse->id,
            'to_agent_id' => $openMarket->id,
            'requested_by' => $openMarket->id,
            'status' => StockTransferStatus::Requested,
            'requires_approval' => true,
        ]);

        $stockCount = StockCount::create([
            'user_id' => $openMarket->id,
            'is_additional_count' => false,
            'status' => 'pending',
            'supervisor_status' => 'verified',
        ]);
        $stockCount->items()->create([
            'product_type_id' => $productType->id,
            'product_name' => $productType->name,
            'grammage' => 100,
            'quantity' => 5,
        ]);

        DamagedStockReturn::factory()->create([
            'user_id' => $openMarket->id,
            'status' => 'pending',
            'supervisor_approved_by' => $supervisor->id,
            'product_type_id' => $productType->id,
        ]);

        SalesRecord::factory()->credit()->create([
            'agent_id' => $openMarket->id,
            'agent_type' => 'open_market',
        ]);

        SalesRecord::factory()->create([
            'agent_id' => $openMarket->id,
            'agent_type' => 'open_market',
            'status' => 'pending',
        ]);

        StockTransfer::create([
            'from_warehouse_id' => $warehouse->id,
            'to_agent_id' => $openMarket->id,
            'status' => StockTransferStatus::Received,
            'requires_approval' => true,
        ]);

        Livewire::test(AccountantStockTransferApprovalWidget::class)
            ->assertTableActionExists('view')
            ->assertCanSeeTableRecords([$pendingTransfer]);

        Livewire::test(AccountantStockCountApprovalWidget::class)
            ->assertTableActionExists('view');

        Livewire::test(AccountantDamagedReturnsWidget::class)
            ->assertTableActionExists('view');

        Livewire::test(AccountantCreditSalesWidget::class)
            ->assertTableActionExists('view');

        Livewire::test(AccountantSalesRecordsWidget::class)
            ->assertTableActionExists('view');

        Livewire::test(AccountantStockMovementsWidget::class)
            ->assertTableActionExists('view');

        Livewire::test(DamagedReturnsBreakdownWidget::class)
            ->assertTableActionExists('view');
    }

    public function test_sales_record_view_action_shows_credit_details(): void
    {
        $accountant = User::factory()->accountant()->create();
        $this->actingAs($accountant);

        $openMarket = User::factory()->openMarket()->create();
        $record = SalesRecord::factory()->credit()->create([
            'agent_id' => $openMarket->id,
            'agent_type' => 'open_market',
            'total_value' => 5000,
        ]);

        $record->collections()->create([
            'collected_amount' => 2000,
            'collected_at' => now(),
            'collected_by' => $accountant->id,
        ]);

        Livewire::test(AccountantCreditSalesWidget::class)
            ->mountTableAction('view', $record->id)
            ->assertHasNoActionErrors();

        $this->assertSame(3000.0, $record->outstandingAmount());
    }

    public function test_rep_sales_widget_dispatches_open_rep_sales_breakdown(): void
    {
        $accountant = User::factory()->accountant()->create();
        $this->actingAs($accountant);

        $rep = User::factory()->rep()->create();

        Livewire::test(AccountantRepSalesWidget::class)
            ->assertTableActionExists('viewRepSales')
            ->callTableAction('viewRepSales', $rep->id)
            ->assertDispatched('open-rep-sales-breakdown');
    }

    public function test_open_rep_sales_breakdown_mounts_modal(): void
    {
        $accountant = User::factory()->accountant()->create();
        $this->actingAs($accountant);

        $rep = User::factory()->rep()->create();

        Livewire::test(AccountantDashboard::class)
            ->call('openRepSalesBreakdown', $rep->id)
            ->assertSet('breakdownUserId', $rep->id)
            ->assertActionMounted('repSalesBreakdown');
    }

    public function test_rep_sales_breakdown_action_shows_picker_without_crash(): void
    {
        $accountant = User::factory()->accountant()->create();
        $this->actingAs($accountant);

        Livewire::test(AccountantDashboard::class)
            ->call('mountAction', 'repSalesBreakdown')
            ->assertActionMounted('repSalesBreakdown');
    }

    public function test_rep_sales_breakdown_action_picker_lists_reps_and_leads(): void
    {
        $accountant = User::factory()->accountant()->create();
        $this->actingAs($accountant);

        $rep = User::factory()->rep()->create(['name' => 'Picker Rep']);
        $lead = User::factory()->lead()->create(['name' => 'Picker Lead']);

        Livewire::test(AccountantDashboard::class)
            ->call('mountAction', 'repSalesBreakdown')
            ->assertMountedActionModalSee('Select a rep or lead to view their sales breakdown')
            ->assertMountedActionModalSee($rep->name)
            ->assertMountedActionModalSee($lead->name);
    }

    public function test_rep_sales_breakdown_table_shows_daily_records_and_drills_down(): void
    {
        $accountant = User::factory()->accountant()->create();
        $this->actingAs($accountant);

        $rep = User::factory()->rep()->create(['name' => 'Daily Rep']);
        $customer = Customer::factory()->repId($rep)->create(['customer_name' => 'Daily Customer']);

        Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'delivered',
            'total_price' => 1000,
            'created_at' => now(),
        ]);
        Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'delivered',
            'total_price' => 500,
            'created_at' => now(),
        ]);

        Livewire::test(RepSalesBreakdownTable::class, ['userId' => $rep->id])
            ->assertSee('Daily Rep')
            ->assertSee('₦1,500.00');

        Livewire::test(RepSalesBreakdownTable::class, ['userId' => $rep->id])
            ->call('selectDate', now()->toDateString())
            ->assertSee('Daily Customer');
    }

    public function test_stock_levels_widget_is_searchable(): void
    {
        $accountant = User::factory()->accountant()->create();
        $this->actingAs($accountant);

        $warehouse = Warehouse::factory()->create();
        $productType = ProductType::factory()->create(['name' => 'Ora herbal mix']);

        Inventory::create([
            'warehouse_id' => $warehouse->id,
            'product_type_id' => $productType->id,
            'grammage' => 100,
            'quantity' => 20,
        ]);

        $agent = User::factory()->openMarket()->create();
        AgentStock::create([
            'user_id' => $agent->id,
            'product_type_id' => $productType->id,
            'product_name' => 'Bitters',
            'grammage' => 200,
            'quantity' => 5,
        ]);

        Livewire::test(AccountantStockLevelsWidget::class)
            ->assertSee('Ora herbal mix')
            ->assertSee('Bitters')
            ->set('search', 'Bitters')
            ->assertSee('Bitters')
            ->assertDontSee('Ora herbal mix');
    }

    public function test_open_stock_movement_breakdown_mounts_modal(): void
    {
        $accountant = User::factory()->accountant()->create();
        $this->actingAs($accountant);

        $agent = User::factory()->openMarket()->create();

        Livewire::test(AccountantDashboard::class)
            ->call('openStockMovementBreakdown', 'agent', $agent->id, 'Ora herbal mix', 100)
            ->assertSet('breakdownEntityType', 'agent')
            ->assertSet('breakdownEntityId', $agent->id)
            ->assertActionMounted('stockMovementBreakdown');
    }

    public function test_stock_movement_action_shows_picker_without_crash(): void
    {
        $accountant = User::factory()->accountant()->create();
        $this->actingAs($accountant);

        Livewire::test(AccountantDashboard::class)
            ->call('mountAction', 'stockMovementBreakdown')
            ->assertActionMounted('stockMovementBreakdown');
    }

    public function test_stock_movement_action_picker_lists_agents_and_warehouses(): void
    {
        $accountant = User::factory()->accountant()->create();
        $this->actingAs($accountant);

        $agent = User::factory()->openMarket()->create(['name' => 'Picker Agent']);
        $productType = ProductType::factory()->create();
        $warehouse = Warehouse::factory()->create(['name' => 'Picker Warehouse']);

        AgentStock::create([
            'user_id' => $agent->id,
            'product_type_id' => $productType->id,
            'product_name' => 'Bitters',
            'grammage' => 200,
            'quantity' => 5,
        ]);

        Livewire::test(AccountantDashboard::class)
            ->call('mountAction', 'stockMovementBreakdown')
            ->assertMountedActionModalSee('Select an agent or warehouse to view their stock movement history')
            ->assertMountedActionModalSee($agent->name)
            ->assertMountedActionModalSee($warehouse->name);
    }

    public function test_stock_movement_breakdown_lists_entity_movements_and_filters(): void
    {
        $accountant = User::factory()->accountant()->create();
        $this->actingAs($accountant);

        $agent = User::factory()->openMarket()->create();
        $productType = ProductType::factory()->create(['name' => 'Ora herbal mix']);
        $warehouse = Warehouse::factory()->create();

        $transfer = StockTransfer::create([
            'from_warehouse_id' => $warehouse->id,
            'to_agent_id' => $agent->id,
            'status' => StockTransferStatus::Received,
            'received_at' => now(),
            'requires_approval' => true,
        ]);
        $transfer->items()->create([
            'product_type_id' => $productType->id,
            'grammage' => 100,
            'quantity' => 10,
        ]);

        $stockCount = StockCount::create([
            'user_id' => $agent->id,
            'is_additional_count' => false,
            'status' => 'approved',
            'approved_at' => now(),
        ]);
        $stockCount->items()->create([
            'product_type_id' => $productType->id,
            'product_name' => $productType->name,
            'grammage' => 100,
            'quantity' => 4,
        ]);

        DamagedStockReturn::factory()->approved()->create([
            'user_id' => $agent->id,
            'product_type_id' => $productType->id,
            'quantity' => 2,
        ]);

        Livewire::test(StockMovementBreakdownTable::class, [
            'entityType' => 'agent',
            'entityId' => $agent->id,
        ])
            ->assertSee('Transfer In')
            ->assertSee('Stock Count')
            ->assertSee('Damaged Return')
            ->set('typeFilter', 'in')
            ->assertSee('Transfer In')
            ->assertDontSee('Stock Count')
            ->assertDontSee('Damaged Return');
    }
}
