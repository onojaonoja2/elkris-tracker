<?php

namespace Tests\Feature\Filament;

use App\Filament\Widgets\UnassignedCustomersWidget;
use App\Models\Customer;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UnassignedCustomersWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_unassigned_customers_in_widget(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $unassigned = Customer::factory()->create();
        $assigned = Customer::factory()->repId(User::factory()->rep()->create())->create();

        Livewire::test(UnassignedCustomersWidget::class)
            ->assertCanSeeTableRecords([$unassigned])
            ->assertCanNotSeeTableRecords([$assigned]);
    }

    public function test_widget_is_hidden_for_managers(): void
    {
        $manager = User::factory()->manager()->create();
        $this->actingAs($manager);

        $this->assertFalse(UnassignedCustomersWidget::canView());
    }

    public function test_admin_can_assign_customer_to_rep_with_pending_acceptance(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $rep = User::factory()->rep()->create();
        $customer = Customer::factory()->create();

        Livewire::test(UnassignedCustomersWidget::class)
            ->callAction(TestAction::make('assignToRep')->table($customer), [
                'rep_id' => $rep->id,
            ]);

        $customer->refresh();

        $this->assertSame($rep->id, $customer->rep_id);
        $this->assertSame('pending', $customer->rep_acceptance_status);
        $this->assertSame('rep', $customer->submission_target_type);
        $this->assertTrue($customer->reps()->where('users.id', $rep->id)->exists());
    }

    public function test_admin_can_assign_customer_to_sales(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $sales = User::factory()->sales()->create();
        $customer = Customer::factory()->create();

        Livewire::test(UnassignedCustomersWidget::class)
            ->callAction(TestAction::make('assignToSales')->table($customer), [
                'sales_id' => $sales->id,
            ]);

        $customer->refresh();

        $this->assertSame($sales->id, $customer->agent_id);
        $this->assertNull($customer->rep_id);
        $this->assertNull($customer->lead_id);
    }

    public function test_admin_can_assign_customer_to_team_lead_with_pending_acceptance(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $lead = User::factory()->lead()->create();
        $customer = Customer::factory()->create();

        Livewire::test(UnassignedCustomersWidget::class)
            ->callAction(TestAction::make('assignToLead')->table($customer), [
                'lead_id' => $lead->id,
            ]);

        $customer->refresh();

        $this->assertSame($lead->id, $customer->lead_id);
        $this->assertSame('pending', $customer->rep_acceptance_status);
        $this->assertSame('lead', $customer->submission_target_type);
        $this->assertTrue($customer->leads()->where('users.id', $lead->id)->exists());
    }

    public function test_admin_can_leave_customer_for_manager_and_revert(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $customer = Customer::factory()->create();

        Livewire::test(UnassignedCustomersWidget::class)
            ->callAction(TestAction::make('leaveForManager')->table($customer));

        $customer->refresh();

        $this->assertNotNull($customer->manager_visible_at);
        $this->assertTrue($customer->isUnassigned());

        Livewire::test(UnassignedCustomersWidget::class)
            ->callAction(TestAction::make('hideFromManager')->table($customer->fresh()));

        $this->assertNull($customer->fresh()->manager_visible_at);
    }

    public function test_assigning_customer_clears_manager_visibility(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $sales = User::factory()->sales()->create();
        $customer = Customer::factory()->create(['manager_visible_at' => now()]);

        Livewire::test(UnassignedCustomersWidget::class)
            ->callAction(TestAction::make('assignToSales')->table($customer), [
                'sales_id' => $sales->id,
            ]);

        $customer->refresh();

        $this->assertNull($customer->manager_visible_at);
    }
}
