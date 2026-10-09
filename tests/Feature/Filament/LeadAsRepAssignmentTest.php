<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Customers\Pages\CreateCustomer;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Widgets\AdminCustomerSearchTable;
use App\Filament\Widgets\ManagerCustomerSubmissionsWidget;
use App\Filament\Widgets\UnassignedCustomersWidget;
use App\Livewire\CustomerBreakdownTable;
use App\Models\Customer;
use App\Models\Lga;
use App\Models\Region;
use App\Models\State;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LeadAsRepAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_unassigned_card_assign_to_rep_with_lead_goes_directly_into_lead_portfolio(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $lead = User::factory()->lead()->create();
        $customer = Customer::factory()->create();

        Livewire::test(UnassignedCustomersWidget::class)
            ->callAction(TestAction::make('assignToRep')->table($customer), [
                'rep_id' => $lead->id,
            ]);

        $customer->refresh();

        $this->assertSame($lead->id, $customer->rep_id);
        $this->assertSame($lead->id, $customer->lead_id);
        $this->assertSame('accepted', $customer->rep_acceptance_status);
        $this->assertSame('lead', $customer->submission_target_type);
        $this->assertTrue($customer->leads()->where('users.id', $lead->id)->exists());
        $this->assertFalse($customer->reps()->where('users.id', $lead->id)->exists());
    }

    public function test_customers_table_assign_to_rep_with_lead_goes_directly_into_lead_portfolio(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $lead = User::factory()->lead()->create();
        $customer = Customer::factory()->create();

        Livewire::test(ListCustomers::class)
            ->mountTableAction('assignToRep', $customer->id)
            ->setTableActionData(['rep_id' => $lead->id])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $customer->refresh();

        $this->assertSame($lead->id, $customer->rep_id);
        $this->assertSame($lead->id, $customer->lead_id);
        $this->assertSame('accepted', $customer->rep_acceptance_status);
        $this->assertTrue($customer->leads()->where('users.id', $lead->id)->exists());
        $this->assertFalse($customer->reps()->where('users.id', $lead->id)->exists());
    }

    public function test_customers_table_assign_to_rep_with_rep_still_awaits_acceptance(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $rep = User::factory()->rep()->create();
        $customer = Customer::factory()->create();

        Livewire::test(ListCustomers::class)
            ->mountTableAction('assignToRep', $customer->id)
            ->setTableActionData(['rep_id' => $rep->id])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $customer->refresh();

        $this->assertSame($rep->id, $customer->rep_id);
        $this->assertSame('pending', $customer->rep_acceptance_status);
    }

    public function test_bulk_assign_to_rep_with_lead_goes_directly_into_lead_portfolio(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $lead = User::factory()->lead()->create();
        $customer = Customer::factory()->create();

        Livewire::test(ListCustomers::class)
            ->callTableBulkAction('bulkAssignToRep', [$customer->id], [
                'rep_id' => $lead->id,
            ]);

        $customer->refresh();

        $this->assertSame($lead->id, $customer->rep_id);
        $this->assertSame($lead->id, $customer->lead_id);
        $this->assertSame('accepted', $customer->rep_acceptance_status);
    }

    public function test_manager_submissions_assign_to_rep_with_lead_goes_directly_into_lead_portfolio(): void
    {
        $manager = User::factory()->manager()->create();
        $this->actingAs($manager);

        $lead = User::factory()->lead()->create();
        $customer = Customer::factory()->state([
            'submission_target_type' => 'manager',
            'lead_id' => $manager->id,
        ])->create();

        Livewire::test(ManagerCustomerSubmissionsWidget::class)
            ->callAction(TestAction::make('assignToRep')->table($customer), [
                'rep_id' => $lead->id,
            ]);

        $customer->refresh();

        $this->assertSame($lead->id, $customer->rep_id);
        $this->assertSame($lead->id, $customer->lead_id);
        $this->assertSame('accepted', $customer->rep_acceptance_status);
        $this->assertTrue($customer->leads()->where('users.id', $lead->id)->exists());
    }

    public function test_admin_customer_search_reassign_rep_with_lead_goes_directly_into_lead_portfolio(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $lead = User::factory()->lead()->create();
        $customer = Customer::factory()->create();

        Livewire::test(AdminCustomerSearchTable::class)
            ->callAction(TestAction::make('reassignRep')->table($customer), [
                'rep_id' => $lead->id,
            ]);

        $customer->refresh();

        $this->assertSame($lead->id, $customer->rep_id);
        $this->assertSame($lead->id, $customer->lead_id);
        $this->assertSame('accepted', $customer->rep_acceptance_status);
    }

    public function test_admin_create_customer_with_lead_selected_as_rep_goes_directly_into_lead_portfolio(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $lead = User::factory()->lead()->create();
        $region = Region::create(['name' => 'South West', 'code' => 'SW']);
        $state = State::create(['name' => 'Lagos', 'code' => 'NG-LA', 'region_id' => $region->id]);
        $lga = Lga::create(['name' => 'Ikeja', 'state_id' => $state->id]);

        Livewire::test(CreateCustomer::class)
            ->fillForm([
                'leads' => [$lead->id],
                'reps' => [$lead->id],
                'customer_name' => 'Ada Buyer',
                'phone_number' => '08012345678',
                'city' => 'Ikeja',
                'state_id' => $state->id,
                'lga_id' => $lga->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $customer = Customer::where('customer_name', 'Ada Buyer')->first();

        $this->assertNotNull($customer);
        $this->assertSame($lead->id, $customer->rep_id);
        $this->assertSame($lead->id, $customer->lead_id);
        $this->assertSame('accepted', $customer->rep_acceptance_status);
        $this->assertSame('lead', $customer->submission_target_type);
        $this->assertTrue($customer->leads()->where('users.id', $lead->id)->exists());
        $this->assertFalse($customer->reps()->where('users.id', $lead->id)->exists());
    }

    public function test_breakdown_counts_lead_owned_customers_under_the_lead_row(): void
    {
        $lead = User::factory()->lead()->create(['name' => 'Larry Lead']);
        $rep = User::factory()->rep()->create(['name' => 'Rita Rep']);

        $leadOwned = Customer::factory()->state([
            'rep_id' => $lead->id,
            'lead_id' => $lead->id,
            'rep_acceptance_status' => 'accepted',
        ])->create();
        Customer::factory()->repId($rep)->create();

        $component = Livewire::test(CustomerBreakdownTable::class);

        $groups = $component->instance()->groups;

        $this->assertTrue($groups->contains(fn ($row) => $row->label === 'Larry Lead' && $row->type === 'lead' && $row->count === 1));
        $this->assertFalse($groups->contains(fn ($row) => $row->key === 'group-legacy' && $row->count > 0));
        $this->assertSame(Customer::assigned()->count(), (int) $groups->sum('count'));

        $component->call('selectUser', $lead->id);

        $customerIds = $component->instance()->customers->pluck('id')->all();

        $this->assertContains($leadOwned->id, $customerIds);
    }
}
