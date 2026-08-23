<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\AdminCustomerSearch;
use App\Filament\Widgets\AdminCustomerSearchTable;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminCustomerSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_customer_search_page(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(AdminCustomerSearch::getUrl())
            ->assertOk();
    }

    public function test_non_admin_cannot_access_customer_search_page(): void
    {
        $sales = User::factory()->sales()->create();

        $this->actingAs($sales)
            ->get(AdminCustomerSearch::getUrl())
            ->assertForbidden();
    }

    public function test_table_lists_all_customers_and_is_searchable(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $findMe = Customer::factory()->create(['customer_name' => 'Findable Customer']);
        $other = Customer::factory()->create(['customer_name' => 'Other Customer']);

        Livewire::test(AdminCustomerSearchTable::class)
            ->assertCanSeeTableRecords([$findMe, $other])
            ->searchTable('Findable')
            ->assertCanSeeTableRecords([$findMe])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_reassign_agent_updates_customer_agent(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $agent = User::factory()->fieldAgent()->create(['name' => 'New Agent']);
        $customer = Customer::factory()->create(['customer_name' => 'Reassign Agent Customer']);

        Livewire::test(AdminCustomerSearchTable::class)
            ->callTableAction('reassignAgent', $customer->id, ['agent_id' => $agent->id])
            ->assertNotified();

        $this->assertSame($agent->id, $customer->fresh()->agent_id);
    }

    public function test_reassign_rep_sets_pending_status_and_pivot(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $lead = User::factory()->lead()->create();
        $rep = User::factory()->rep()->create(['name' => 'New Rep', 'lead_id' => $lead->id]);
        $customer = Customer::factory()->leadId($lead)->create(['customer_name' => 'Reassign Rep Customer']);

        Livewire::test(AdminCustomerSearchTable::class)
            ->callTableAction('reassignRep', $customer->id, ['rep_id' => $rep->id])
            ->assertNotified();

        $customer->refresh();
        $this->assertSame($rep->id, $customer->rep_id);
        $this->assertSame('pending', $customer->rep_acceptance_status);
        $this->assertDatabaseHas('customer_rep', [
            'customer_id' => $customer->id,
            'user_id' => $rep->id,
        ]);
    }

    public function test_reassign_lead_updates_customer_lead_and_pivot(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $lead = User::factory()->lead()->create(['name' => 'New Lead']);
        $customer = Customer::factory()->create(['customer_name' => 'Reassign Lead Customer']);

        Livewire::test(AdminCustomerSearchTable::class)
            ->callTableAction('reassignLead', $customer->id, ['lead_id' => $lead->id])
            ->assertNotified();

        $customer->refresh();
        $this->assertSame($lead->id, $customer->lead_id);
        $this->assertDatabaseHas('customer_lead', [
            'customer_id' => $customer->id,
            'user_id' => $lead->id,
        ]);
    }

    public function test_admin_can_delete_customer(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $customer = Customer::factory()->create(['customer_name' => 'Delete Me Customer']);

        Livewire::test(AdminCustomerSearchTable::class)
            ->callTableAction('delete', $customer->id)
            ->assertNotified();

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }
}
