<?php

namespace Tests\Feature\Livewire;

use App\Livewire\CustomerBreakdownTable;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerBreakdownTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_breakdown_counts_all_time_customers_regardless_of_dashboard_date_scope(): void
    {
        Session::put('dashboard_date_from', now()->startOfDay()->toDateTimeString());
        Session::put('dashboard_date_to', now()->endOfDay()->toDateTimeString());

        $csr = User::factory()->communitySalesRepresentative()->create(['name' => 'Cara CSR']);
        Customer::factory()->agentId($csr)->create([
            'customer_name' => 'Old Buyer',
            'created_at' => now()->subDays(10),
        ]);

        $component = Livewire::test(CustomerBreakdownTable::class);

        $groups = $component->instance()->groups;

        $this->assertTrue($groups->contains(fn ($row) => $row->key === 'group-csr' && $row->count === 1));
    }

    public function test_groups_list_individual_epas_and_team_leads_with_counts(): void
    {
        $rep = User::factory()->rep()->create(['name' => 'Rita Rep']);
        $lead = User::factory()->lead()->create(['name' => 'Larry Lead']);
        $leadRep = User::factory()->rep()->create(['name' => 'Paula Rep', 'lead_id' => $lead->id]);

        Customer::factory()->repId($rep)->count(2)->create();
        Customer::factory()->leadId($lead)->create();
        Customer::factory()->repId($leadRep)->create();

        $component = Livewire::test(CustomerBreakdownTable::class);

        $groups = $component->instance()->groups;

        $this->assertTrue($groups->contains(fn ($row) => $row->label === 'Rita Rep' && $row->type === 'rep' && $row->count === 2));
        $this->assertTrue($groups->contains(fn ($row) => $row->label === 'Larry Lead' && $row->type === 'lead' && $row->count === 2));
    }

    public function test_groups_include_high_level_agent_groups_and_unassigned(): void
    {
        $csr = User::factory()->communitySalesRepresentative()->create();
        $openMarket = User::factory()->openMarket()->create();
        $retailMarket = User::factory()->retailMarket()->create();

        Customer::factory()->agentId($csr)->count(3)->create();
        Customer::factory()->agentId($openMarket)->create();
        Customer::factory()->agentId($retailMarket)->count(2)->create();
        Customer::factory()->create();

        $component = Livewire::test(CustomerBreakdownTable::class);

        $groups = $component->instance()->groups;

        $this->assertTrue($groups->contains(fn ($row) => $row->key === 'group-csr' && $row->count === 3));
        $this->assertTrue($groups->contains(fn ($row) => $row->key === 'group-open_market' && $row->count === 1));
        $this->assertTrue($groups->contains(fn ($row) => $row->key === 'group-retail_market' && $row->count === 2));
        $this->assertTrue($groups->contains(fn ($row) => $row->key === 'group-unassigned' && $row->count === 1));
    }

    public function test_drill_into_group_reveals_agents_then_customers(): void
    {
        $csr = User::factory()->communitySalesRepresentative()->create(['name' => 'Cara CSR']);
        Customer::factory()->agentId($csr)->create(['customer_name' => 'Kelechi Buyer']);

        $component = Livewire::test(CustomerBreakdownTable::class)
            ->call('selectGroup', 'csr');

        $this->assertSame('agents', $component->instance()->level);
        $this->assertTrue($component->instance()->agents->contains(fn ($row) => $row->name === 'Cara CSR' && $row->count === 1));

        $component->call('selectAgent', $csr->id);

        $this->assertSame('customers', $component->instance()->level);
        $this->assertTrue($component->instance()->customers->contains(fn ($customer) => $customer->customer_name === 'Kelechi Buyer'));

        $component->call('back');
        $this->assertSame('agents', $component->instance()->level);

        $component->call('back');
        $this->assertSame('groups', $component->instance()->level);
    }

    public function test_drilling_into_rep_shows_customer_details_with_order_totals(): void
    {
        $rep = User::factory()->rep()->create(['name' => 'Rita Rep']);
        $customer = Customer::factory()->repId($rep)->create(['customer_name' => 'Ada Buyer']);

        $customer->orders()->create([
            'status' => 'delivered',
            'total_price' => 2500,
            'is_migrated_order' => false,
        ]);

        $component = Livewire::test(CustomerBreakdownTable::class)
            ->call('selectUser', $rep->id);

        $customers = $component->instance()->customers;

        $this->assertSame(1, $customers->total());
        $this->assertSame('Ada Buyer', $customers->first()->customer_name);
        $this->assertSame(1, (int) $customers->first()->orders_count);
        $this->assertEquals(2500.0, (float) $customers->first()->orders_total);
    }
}
