<?php

namespace Tests\Feature\Dashboards;

use App\Filament\Pages\AccountantDashboard;
use App\Filament\Pages\SupervisorDashboard;
use App\Livewire\RevenueBreakdownTable;
use App\Models\SalesRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AccountantRevenueBreakdownTest extends TestCase
{
    use RefreshDatabase;

    public function test_accountant_can_mount_revenue_breakdown_action(): void
    {
        $accountant = User::factory()->accountant()->create();
        $this->actingAs($accountant);

        Livewire::test(AccountantDashboard::class)
            ->call('mountAction', 'revenueBreakdown')
            ->assertActionMounted('revenueBreakdown')
            ->assertMountedActionModalSee('Revenue Breakdown by Agent');
    }

    public function test_revenue_breakdown_aggregates_all_selling_agents_for_accountant(): void
    {
        $accountant = User::factory()->accountant()->create();
        $this->actingAs($accountant);

        $fieldAgent = User::factory()->fieldAgent()->create(['name' => 'Field Agent Revenue']);
        $csr = User::factory()->communitySalesRepresentative()->create(['name' => 'CSR Revenue']);
        $openMarket = User::factory()->openMarket()->create(['name' => 'Open Market Revenue']);
        $retailMarket = User::factory()->retailMarket()->create(['name' => 'Retail Market Revenue']);
        $rep = User::factory()->rep()->create(['name' => 'Rep Revenue']);
        $lead = User::factory()->lead()->create(['name' => 'Lead Revenue']);

        foreach ([$fieldAgent, $csr, $openMarket, $retailMarket, $rep, $lead] as $agent) {
            SalesRecord::factory()->approved()->create([
                'agent_id' => $agent->id,
                'agent_type' => $agent->role,
                'total_value' => 1000,
            ]);
        }

        Livewire::test(RevenueBreakdownTable::class)
            ->assertSee('Field Agent Revenue')
            ->assertSee('CSR Revenue')
            ->assertSee('Open Market Revenue')
            ->assertSee('Retail Market Revenue')
            ->assertSee('Rep Revenue')
            ->assertSee('Lead Revenue')
            ->assertSee('₦1,000.00');
    }

    public function test_revenue_breakdown_respects_accountant_dashboard_date_filter(): void
    {
        $accountant = User::factory()->accountant()->create();
        $this->actingAs($accountant);

        session([
            'dashboard_date_from' => now()->startOfDay()->toDateTimeString(),
            'dashboard_date_to' => now()->endOfDay()->toDateTimeString(),
        ]);

        $csr = User::factory()->communitySalesRepresentative()->create(['name' => 'Date Filter Agent']);

        SalesRecord::factory()->approved()->create([
            'agent_id' => $csr->id,
            'agent_type' => 'community_sales_representative',
            'total_value' => 1000,
            'created_at' => now(),
        ]);

        SalesRecord::factory()->approved()->create([
            'agent_id' => $csr->id,
            'agent_type' => 'community_sales_representative',
            'total_value' => 1000,
            'created_at' => now()->subDays(60),
        ]);

        Livewire::test(RevenueBreakdownTable::class)
            ->assertSee('Date Filter Agent')
            ->assertSee('₦1,000.00')
            ->assertDontSee('₦2,000.00');
    }

    public function test_supervisor_revenue_breakdown_still_shows_only_csrs(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $this->actingAs($supervisor);

        $csr = User::factory()->communitySalesRepresentative()->create(['name' => 'Supervisor CSR']);
        $fieldAgent = User::factory()->fieldAgent()->create(['name' => 'Supervisor Field Agent']);

        SalesRecord::factory()->approved()->create([
            'agent_id' => $csr->id,
            'agent_type' => 'community_sales_representative',
            'total_value' => 500,
        ]);

        SalesRecord::factory()->approved()->create([
            'agent_id' => $fieldAgent->id,
            'agent_type' => 'field_agent',
            'total_value' => 500,
        ]);

        Livewire::test(RevenueBreakdownTable::class)
            ->assertSee('Supervisor CSR')
            ->assertDontSee('Supervisor Field Agent');
    }

    public function test_supervisor_dashboard_still_mounts_revenue_breakdown(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $this->actingAs($supervisor);

        Livewire::test(SupervisorDashboard::class)
            ->call('openRevenueBreakdown')
            ->assertSet('breakdownType', 'revenue')
            ->assertActionMounted('revenueBreakdown');
    }
}
