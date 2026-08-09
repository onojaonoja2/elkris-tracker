<?php

namespace Tests\Feature\Filament;

use App\Livewire\TeamSalesBreakdownTable;
use App\Models\SalesRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use Tests\TestCase;

class LeadTeamSalesBreakdownTest extends TestCase
{
    use RefreshDatabase;

    private User $lead;

    private User $rep;

    private User $repCsr;

    private User $leadCsr;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lead = User::factory()->lead()->create();
        $this->rep = User::factory()->rep()->create([
            'lead_id' => $this->lead->id,
        ]);
        $this->repCsr = User::factory()->communitySalesRepresentative()->create([
            'portfolio_agent_id' => $this->rep->id,
        ]);
        $this->leadCsr = User::factory()->communitySalesRepresentative()->create([
            'portfolio_agent_id' => $this->lead->id,
        ]);

        $this->actingAs($this->lead);

        Session::put('dashboard_date_from', now()->startOfDay()->toDateTimeString());
        Session::put('dashboard_date_to', now()->endOfDay()->toDateTimeString());
    }

    public function test_summary_shows_reps_and_leads_own_attached_csrs_approved_in_range(): void
    {
        SalesRecord::factory()->approved()->create([
            'agent_id' => $this->repCsr->id,
            'total_value' => 1000,
        ]);

        SalesRecord::factory()->approved()->create([
            'agent_id' => $this->leadCsr->id,
            'total_value' => 500,
        ]);

        SalesRecord::factory()->approved()->create([
            'agent_id' => $this->leadCsr->id,
            'total_value' => 9000,
            'created_at' => now()->subDays(5),
        ]);

        SalesRecord::factory()->create([
            'agent_id' => $this->repCsr->id,
            'total_value' => 7000,
            'status' => 'pending',
        ]);

        $unattachedCsr = User::factory()->communitySalesRepresentative()->create();
        SalesRecord::factory()->approved()->create([
            'agent_id' => $unattachedCsr->id,
            'total_value' => 20000,
        ]);

        Livewire::test(TeamSalesBreakdownTable::class)
            ->assertSet('selectedAgentId', null)
            ->assertSee($this->repCsr->name)
            ->assertSee($this->leadCsr->name)
            ->assertSee('1,000.00')
            ->assertSee('500.00')
            ->assertDontSee($unattachedCsr->name);
    }

    public function test_summary_lists_attached_csr_even_without_sales_in_range(): void
    {
        $idleCsr = User::factory()->communitySalesRepresentative()->create([
            'portfolio_agent_id' => $this->lead->id,
        ]);

        SalesRecord::factory()->approved()->create([
            'agent_id' => $this->repCsr->id,
            'total_value' => 1000,
        ]);

        Livewire::test(TeamSalesBreakdownTable::class)
            ->assertSee($this->repCsr->name)
            ->assertSee($this->leadCsr->name)
            ->assertSee($idleCsr->name)
            ->assertSee('1,000.00');
    }

    public function test_drill_down_lists_individual_sales_for_selected_csr(): void
    {
        $record = SalesRecord::factory()->approved()->create([
            'agent_id' => $this->leadCsr->id,
            'total_value' => 1500,
            'customer_name' => 'Jane Doe',
        ]);

        Livewire::test(TeamSalesBreakdownTable::class)
            ->call('selectAgent', $this->leadCsr->id)
            ->assertSet('selectedAgentId', $this->leadCsr->id)
            ->assertSee('Jane Doe')
            ->assertSee('1,500.00');

        $this->assertDatabaseHas('sales_records', ['id' => $record->id]);
    }

    public function test_back_to_summary_resets_selection(): void
    {
        Livewire::test(TeamSalesBreakdownTable::class)
            ->call('selectAgent', $this->repCsr->id)
            ->assertSet('selectedAgentId', $this->repCsr->id)
            ->call('backToSummary')
            ->assertSet('selectedAgentId', null);
    }

    public function test_time_bound_excludes_out_of_range_records(): void
    {
        SalesRecord::factory()->approved()->create([
            'agent_id' => $this->leadCsr->id,
            'total_value' => 500,
        ]);

        SalesRecord::factory()->approved()->create([
            'agent_id' => $this->leadCsr->id,
            'total_value' => 8000,
            'created_at' => now()->addDays(3),
        ]);

        Livewire::test(TeamSalesBreakdownTable::class)
            ->assertSee('500.00')
            ->assertDontSee('8,000.00');
    }

    public function test_component_is_forbidden_for_non_rep_and_non_lead_users(): void
    {
        $sales = User::factory()->sales()->create();
        $this->actingAs($sales);

        Livewire::test(TeamSalesBreakdownTable::class)
            ->assertForbidden();
    }
}
