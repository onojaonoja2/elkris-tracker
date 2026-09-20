<?php

namespace Tests\Feature\Filament;

use App\Enums\StockTransferStatus;
use App\Filament\Pages\ManagerDashboard;
use App\Filament\Pages\SupervisorDashboard;
use App\Filament\Widgets\AgentCustomerViewWidget;
use App\Filament\Widgets\ManagerCreditSalesWidget;
use App\Filament\Widgets\ManagerPeopleByStateWidget;
use App\Filament\Widgets\ManagerSalesRecordsByStateWidget;
use App\Filament\Widgets\SupervisorCreditSalesWidget;
use App\Filament\Widgets\SupervisorCsrListWidget;
use App\Filament\Widgets\SupervisorSalesRecordsWidget;
use App\Filament\Widgets\SupervisorStockTransferApprovalWidget;
use App\Livewire\StateBreakdownTable;
use App\Models\Customer;
use App\Models\Lga;
use App\Models\Region;
use App\Models\SalesRecord;
use App\Models\State;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManagerSupervisorParityTest extends TestCase
{
    use RefreshDatabase;

    private function lagos(): State
    {
        $region = Region::create(['name' => 'South West', 'code' => 'SW']);
        $state = State::create(['name' => 'Lagos', 'code' => 'LA', 'region_id' => $region->id]);
        Lga::create(['name' => 'Ikeja', 'state_id' => $state->id]);

        return $state->fresh();
    }

    public function test_manager_dashboard_renders_with_supervisor_widgets(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->get('/admin/manager-dashboard')
            ->assertOk();

        Livewire::test(SupervisorCsrListWidget::class)->assertSee('CSR Overview');
        Livewire::test(SupervisorSalesRecordsWidget::class)->assertSee('Recent Sales Records');
        Livewire::test(SupervisorCreditSalesWidget::class)->assertSee('Credit Sales by CSR');
    }

    public function test_agent_customer_overview_renders_for_manager_and_supervisor(): void
    {
        $manager = User::factory()->manager()->create();
        $supervisor = User::factory()->supervisor()->create();
        $csr = User::factory()->communitySalesRepresentative()->create([
            'name' => 'Overview Agent',
            'lead_id' => $supervisor->id,
        ]);
        Customer::factory()->create(['agent_id' => $csr->id, 'customer_name' => 'Overview Customer']);

        $this->actingAs($manager);

        Livewire::test(AgentCustomerViewWidget::class)
            ->assertSee('Agent Customer Overview')
            ->assertSee('Overview Customer');

        $this->actingAs($supervisor);

        Livewire::test(AgentCustomerViewWidget::class)
            ->assertSee('Agent Customer Overview')
            ->assertSee('Overview Customer');
    }

    public function test_supervisor_and_gm_dashboards_still_render(): void
    {
        $supervisor = User::factory()->supervisor()->create();

        $this->actingAs($supervisor)
            ->get('/admin/supervisor-dashboard')
            ->assertOk();

        $gm = User::factory()->generalManager()->create();

        $this->actingAs($gm)
            ->get('/admin/general-manager-dashboard')
            ->assertOk();
    }

    public function test_manager_dashboard_has_supervisor_breakdown_buttons(): void
    {
        $manager = User::factory()->manager()->create();
        $this->actingAs($manager);

        Livewire::test(ManagerDashboard::class)
            ->call('openRevenueBreakdown')
            ->assertSet('breakdownType', 'revenue')
            ->assertActionMounted('revenueBreakdown');

        Livewire::test(ManagerDashboard::class)
            ->call('openCsrOrderBreakdown')
            ->assertSet('breakdownType', 'csr_order')
            ->assertActionMounted('csrOrderBreakdown');
    }

    public function test_manager_dashboard_removed_items_and_add_user(): void
    {
        $manager = User::factory()->manager()->create();
        $this->actingAs($manager);

        Livewire::test(ManagerDashboard::class)
            ->assertActionExists('addUser')
            ->assertActionDoesNotExist('create_user')
            ->assertActionDoesNotExist('filter_date')
            ->assertDontSee('Total Orders Per City')
            ->assertDontSee('Add Agent');
    }

    public function test_manager_filter_action_writes_shared_dashboard_date_keys(): void
    {
        $manager = User::factory()->manager()->create();
        $this->actingAs($manager);

        Livewire::test(ManagerDashboard::class)
            ->callAction('filterDates', data: [
                'date_from' => now()->subDays(7)->toDateString(),
                'date_to' => now()->toDateString(),
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(now()->subDays(7)->toDateString(), session()->get('dashboard_date_from'));
        $this->assertSame(now()->toDateString(), session()->get('dashboard_date_to'));
    }

    public function test_manager_can_approve_csr_stock_transfer(): void
    {
        $manager = User::factory()->manager()->create();
        $csr = User::factory()->communitySalesRepresentative()->create();
        $warehouse = Warehouse::factory()->create();

        $transfer = StockTransfer::create([
            'from_warehouse_id' => $warehouse->id,
            'to_agent_id' => $csr->id,
            'requested_by' => $csr->id,
            'status' => StockTransferStatus::Requested,
            'requires_approval' => true,
        ]);

        $this->actingAs($manager);

        Livewire::test(SupervisorStockTransferApprovalWidget::class)
            ->assertCanSeeTableRecords([$transfer])
            ->callTableAction('supervisorApprove', $transfer->id)
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('stock_transfers', [
            'id' => $transfer->id,
            'status' => StockTransferStatus::Approved,
            'supervisor_approved_by' => $manager->id,
        ]);
    }

    public function test_people_by_state_paginates_five_and_exports(): void
    {
        $manager = User::factory()->manager()->create();
        $this->actingAs($manager);

        $region = Region::create(['name' => 'Test Region', 'code' => 'TR']);
        $states = collect();
        foreach (['Abia', 'Adamawa', 'Akwa Ibom', 'Anambra', 'Bauchi', 'Bayelsa'] as $index => $name) {
            $states->push(State::create(['name' => $name, 'code' => 'S'.$index, 'region_id' => $region->id]));
        }

        Livewire::test(ManagerPeopleByStateWidget::class)
            ->assertCanSeeTableRecords($states->sortBy('name')->take(5)->values())
            ->assertCanNotSeeTableRecords($states->sortBy('name')->skip(5)->values())
            ->callTableAction('export')
            ->assertFileDownloaded();
    }

    public function test_people_by_state_view_opens_record_list_modal(): void
    {
        $manager = User::factory()->manager()->create();
        $this->actingAs($manager);

        $state = $this->lagos();
        $lga = $state->lgas()->first();
        $csr = User::factory()->communitySalesRepresentative()->create([
            'name' => 'Lagos Csr Person',
            'lga_id' => $lga->id,
        ]);

        Livewire::test(ManagerPeopleByStateWidget::class)
            ->mountTableAction('viewStateBreakdown', $state->id)
            ->assertMountedActionModalSee('Lagos Csr Person');
    }

    public function test_state_breakdown_table_lists_searches_and_exports(): void
    {
        $manager = User::factory()->manager()->create();
        $this->actingAs($manager);

        $state = $this->lagos();
        $lga = $state->lgas()->first();
        $csr = User::factory()->communitySalesRepresentative()->create([
            'name' => 'Breakdown Csr',
            'lga_id' => $lga->id,
        ]);
        $other = User::factory()->communitySalesRepresentative()->create(['name' => 'Elsewhere Csr']);

        SalesRecord::factory()->approved()->create([
            'agent_id' => $csr->id,
            'agent_type' => 'community_sales_representative',
            'customer_name' => 'Breakdown Customer',
        ]);

        Livewire::test(StateBreakdownTable::class, ['entity' => 'people', 'stateId' => $state->id])
            ->assertSee('Breakdown Csr')
            ->assertDontSee('Elsewhere Csr')
            ->set('search', 'Breakdown')
            ->assertSee('Breakdown Csr')
            ->call('exportCsv')
            ->assertFileDownloaded();

        Livewire::test(StateBreakdownTable::class, ['entity' => 'sales', 'stateId' => $state->id])
            ->assertSee('Breakdown Customer')
            ->call('exportCsv')
            ->assertFileDownloaded();
    }

    public function test_sales_and_credit_tables_have_view_and_export(): void
    {
        $manager = User::factory()->manager()->create();
        $this->actingAs($manager);

        $state = $this->lagos();
        $lga = $state->lgas()->first();
        $csr = User::factory()->communitySalesRepresentative()->create(['lga_id' => $lga->id]);

        SalesRecord::factory()->approved()->create([
            'agent_id' => $csr->id,
            'agent_type' => 'community_sales_representative',
            'customer_name' => 'Lagos Sales Customer',
        ]);
        SalesRecord::factory()->credit()->create([
            'agent_id' => $csr->id,
            'agent_type' => 'community_sales_representative',
            'customer_name' => 'Lagos Credit Customer',
        ]);

        Livewire::test(ManagerSalesRecordsByStateWidget::class)
            ->mountTableAction('viewStateBreakdown', $state->id)
            ->assertMountedActionModalSee('Lagos Sales Customer');

        Livewire::test(ManagerSalesRecordsByStateWidget::class)
            ->callTableAction('export')
            ->assertFileDownloaded();

        Livewire::test(ManagerCreditSalesWidget::class)
            ->mountTableAction('viewStateBreakdown', $state->id)
            ->assertMountedActionModalSee('Lagos Credit Customer');

        Livewire::test(ManagerCreditSalesWidget::class)
            ->callTableAction('export')
            ->assertFileDownloaded();
    }

    public function test_supervisor_dashboard_unaffected_by_manager_changes(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $this->actingAs($supervisor);

        Livewire::test(SupervisorDashboard::class)
            ->callAction('filterDates', data: [
                'date_from' => now()->subDays(3)->toDateString(),
                'date_to' => now()->toDateString(),
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(now()->subDays(3)->toDateString(), session()->get('dashboard_date_from'));
    }
}
