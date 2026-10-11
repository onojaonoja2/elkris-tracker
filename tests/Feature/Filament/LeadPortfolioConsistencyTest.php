<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\PortfolioResource\Pages\ListPortfolios;
use App\Filament\Widgets\AdminCustomerSearchTable;
use App\Filament\Widgets\LeadPendingAssignmentsWidget;
use App\Filament\Widgets\LeadPersonalPortfolioWidget;
use App\Filament\Widgets\LeadPortfolioWidget;
use App\Filament\Widgets\LeadStatsWidget;
use App\Filament\Widgets\ManagerConversionWidget;
use App\Filament\Widgets\ManagerCustomerSubmissionsWidget;
use App\Filament\Widgets\ManagerPortfolioPerAgentWidget;
use App\Filament\Widgets\UnassignedCustomersWidget;
use App\Models\Customer;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LeadPortfolioConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_assigned_customer_appears_in_all_lead_portfolio_surfaces(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $lead = User::factory()->lead()->create(['name' => 'Lead One']);
        $customer = Customer::factory()->create(['customer_name' => 'Ada Buyer']);

        Livewire::test(UnassignedCustomersWidget::class)
            ->callAction(TestAction::make('assignToRep')->table($customer), [
                'rep_id' => $lead->id,
            ]);

        $this->assertLeadPortfolioSurfacesInclude($lead, $customer);
    }

    public function test_retail_submission_accepted_by_lead_appears_in_all_lead_portfolio_surfaces(): void
    {
        $manager = User::factory()->manager()->create();
        $this->actingAs($manager);

        $lead = User::factory()->lead()->create(['name' => 'Lead One']);
        $retailAgent = User::factory()->retailMarket()->create();

        $customer = Customer::factory()->state([
            'customer_name' => 'Retail Buyer',
            'agent_id' => $retailAgent->id,
            'lead_id' => $manager->id,
            'submission_target_type' => 'manager',
        ])->create();

        Livewire::test(ManagerCustomerSubmissionsWidget::class)
            ->callAction(TestAction::make('assignToLead')->table($customer), [
                'lead_id' => $lead->id,
            ]);

        $customer->refresh();

        $this->assertSame($lead->id, $customer->lead_id);
        $this->assertSame('pending', $customer->rep_acceptance_status);
        $this->assertSame('lead', $customer->submission_target_type);
        $this->assertTrue($customer->leads()->where('users.id', $lead->id)->exists());

        $this->actingAs($lead);

        Livewire::test(LeadPendingAssignmentsWidget::class)
            ->callAction(TestAction::make('accept')->table($customer->fresh()));

        $customer->refresh();

        $this->assertSame($lead->id, $customer->rep_id);
        $this->assertSame($lead->id, $customer->lead_id);
        $this->assertSame('accepted', $customer->rep_acceptance_status);
        $this->assertTrue($customer->leads()->where('users.id', $lead->id)->exists());
        $this->assertFalse($customer->reps()->where('users.id', $lead->id)->exists());

        $this->assertLeadPortfolioSurfacesInclude($lead, $customer);
    }

    public function test_reassigning_customer_with_old_rep_to_lead_clears_rep_pivot(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $lead = User::factory()->lead()->create(['name' => 'Lead One']);
        $oldRep = User::factory()->rep()->create();

        $customer = Customer::factory()->state([
            'rep_id' => $oldRep->id,
            'rep_acceptance_status' => 'accepted',
        ])->create();
        $customer->reps()->syncWithoutDetaching([$oldRep->id]);

        Livewire::test(AdminCustomerSearchTable::class)
            ->callAction(TestAction::make('reassignRep')->table($customer), [
                'rep_id' => $lead->id,
            ]);

        $customer->refresh();

        $this->assertSame($lead->id, $customer->rep_id);
        $this->assertSame($lead->id, $customer->lead_id);
        $this->assertSame('accepted', $customer->rep_acceptance_status);
        $this->assertFalse($customer->reps()->where('users.id', $oldRep->id)->exists());
        $this->assertFalse($customer->reps()->where('users.id', $lead->id)->exists());

        $this->assertLeadPortfolioSurfacesInclude($lead, $customer);
    }

    public function test_lead_portfolio_tab_shows_customer_despite_stale_pivot_rows(): void
    {
        $lead = User::factory()->lead()->create(['name' => 'Lead One']);
        $otherLead = User::factory()->lead()->create();
        $staleRep = User::factory()->rep()->create();

        $customer = Customer::factory()->state([
            'customer_name' => 'Stale Pivot Buyer',
            'lead_id' => $lead->id,
            'rep_id' => $lead->id,
            'rep_acceptance_status' => 'accepted',
        ])->create();

        $customer->leads()->syncWithoutDetaching([$lead->id, $otherLead->id]);
        $customer->reps()->syncWithoutDetaching([$staleRep->id]);

        $this->actingAs($lead);

        Livewire::test(ListPortfolios::class)
            ->assertCanSeeTableRecords([$customer]);

        Livewire::test(LeadPersonalPortfolioWidget::class)
            ->assertCanSeeTableRecords([$customer]);
    }

    public function test_lead_portfolio_tab_excludes_team_rep_customers(): void
    {
        $lead = User::factory()->lead()->create(['name' => 'Lead One']);
        $teamRep = User::factory()->rep()->create(['lead_id' => $lead->id]);

        $teamCustomer = Customer::factory()->state([
            'customer_name' => 'Team Rep Buyer',
            'lead_id' => $lead->id,
            'rep_id' => $teamRep->id,
            'rep_acceptance_status' => 'accepted',
        ])->create();

        $this->actingAs($lead);

        Livewire::test(ListPortfolios::class)
            ->assertCanNotSeeTableRecords([$teamCustomer]);

        Livewire::test(LeadPersonalPortfolioWidget::class)
            ->assertCanNotSeeTableRecords([$teamCustomer]);
    }

    private function assertLeadPortfolioSurfacesInclude(User $lead, Customer $customer): void
    {
        $this->actingAs($lead);

        Livewire::test(LeadPersonalPortfolioWidget::class)
            ->assertCanSeeTableRecords([$customer]);

        Livewire::test(LeadPortfolioWidget::class)
            ->assertCanSeeTableRecords([$customer]);

        $component = Livewire::test(LeadStatsWidget::class)->instance();
        $stats = (new \ReflectionMethod(LeadStatsWidget::class, 'getStats'))->invoke($component);
        $byLabel = collect($stats)->keyBy(fn ($stat) => $stat->getLabel());

        $this->assertSame(1, $byLabel['Portfolio']->getValue());

        $manager = User::factory()->manager()->create();
        $this->actingAs($manager);

        Livewire::test(ManagerPortfolioPerAgentWidget::class)
            ->assertTableColumnStateSet('customer_count', 1, $lead->id);

        Livewire::test(ManagerConversionWidget::class)
            ->assertTableColumnStateSet('total_customers', 1, $lead->id)
            ->assertTableColumnStateSet('converted', 0, $lead->id);
    }
}
