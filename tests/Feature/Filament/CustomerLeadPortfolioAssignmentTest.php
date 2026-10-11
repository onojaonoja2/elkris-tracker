<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerLeadPortfolioAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_assign_to_lead_enters_lead_acceptance_flow(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $lead = User::factory()->lead()->create();
        $customer = Customer::factory()->create();

        Livewire::test(ListCustomers::class)
            ->mountTableAction('assignToLead', $customer->id)
            ->setTableActionData(['lead_id' => $lead->id])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $customer->refresh();

        $this->assertSame($lead->id, $customer->lead_id);
        $this->assertSame('lead', $customer->submission_target_type);
        $this->assertSame('pending', $customer->rep_acceptance_status);
        $this->assertNull($customer->rep_id);
        $this->assertTrue($customer->leads()->where('users.id', $lead->id)->exists());
    }

    public function test_assign_to_lead_keeps_rep_flow_when_customer_already_has_rep(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $lead = User::factory()->lead()->create();
        $rep = User::factory()->rep()->create();
        $customer = Customer::factory()->state([
            'rep_id' => $rep->id,
            'rep_acceptance_status' => 'accepted',
            'submission_target_type' => 'rep',
        ])->create();

        Livewire::test(ListCustomers::class)
            ->mountTableAction('assignToLead', $customer->id)
            ->setTableActionData(['lead_id' => $lead->id])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $customer->refresh();

        $this->assertSame($lead->id, $customer->lead_id);
        $this->assertSame($rep->id, $customer->rep_id);
        $this->assertSame('accepted', $customer->rep_acceptance_status);
    }
}
