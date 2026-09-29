<?php

namespace Tests\Feature\Livewire;

use App\Livewire\CustomersAddedTodayTable;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CustomersAddedTodayTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_to_today_and_lists_only_todays_customers(): void
    {
        $agent = User::factory()->communitySalesRepresentative()->create(['name' => 'Cara CSR']);

        Customer::factory()->agentId($agent)->create(['customer_name' => 'Today Buyer']);
        Customer::factory()->agentId($agent)->create([
            'customer_name' => 'Yesterday Buyer',
            'created_at' => now()->subDay(),
        ]);

        $component = Livewire::test(CustomersAddedTodayTable::class);

        $customers = $component->instance()->customers;

        $this->assertSame(1, $customers->total());
        $this->assertSame('Today Buyer', $customers->first()->customer_name);
        $this->assertSame('Cara CSR', $customers->first()->agent->name);
    }

    public function test_date_range_filter_lists_customers_across_days(): void
    {
        $agent = User::factory()->communitySalesRepresentative()->create();

        Customer::factory()->agentId($agent)->create(['customer_name' => 'Today Buyer']);
        Customer::factory()->agentId($agent)->create([
            'customer_name' => 'Yesterday Buyer',
            'created_at' => now()->subDay(),
        ]);
        Customer::factory()->agentId($agent)->create([
            'customer_name' => 'Old Buyer',
            'created_at' => now()->subDays(10),
        ]);

        $component = Livewire::test(CustomersAddedTodayTable::class)
            ->set('dateFrom', now()->subDays(3)->toDateString())
            ->set('dateTo', now()->toDateString());

        $names = $component->instance()->customers->pluck('customer_name')->all();

        $this->assertContains('Today Buyer', $names);
        $this->assertContains('Yesterday Buyer', $names);
        $this->assertNotContains('Old Buyer', $names);
    }

    public function test_shows_what_the_customer_has_ordered_so_far(): void
    {
        $agent = User::factory()->openMarket()->create(['name' => 'Obi Open']);

        $customer = Customer::factory()->agentId($agent)->create(['customer_name' => 'Ngozi Buyer']);

        $customer->orders()->create([
            'status' => 'delivered',
            'total_price' => 4000,
            'is_migrated_order' => false,
        ]);
        $customer->orders()->create([
            'status' => 'delivered',
            'total_price' => 1500,
            'is_migrated_order' => false,
        ]);

        $component = Livewire::test(CustomersAddedTodayTable::class);

        $row = $component->instance()->customers->first();

        $this->assertSame(2, (int) $row->orders_count);
        $this->assertEquals(5500.0, (float) $row->orders_total);
    }
}
