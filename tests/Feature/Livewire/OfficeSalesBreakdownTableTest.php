<?php

namespace Tests\Feature\Livewire;

use App\Livewire\OfficeSalesBreakdownTable;
use App\Models\SalesRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OfficeSalesBreakdownTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_role_only_sees_own_summary_row(): void
    {
        $sales = User::factory()->sales()->create();
        $other = User::factory()->sales()->create();

        $this->actingAs($sales);

        $this->officeSale($sales, 'approved', 20000);
        $this->officeSale($other, 'approved', 90000);

        Livewire::test(OfficeSalesBreakdownTable::class)
            ->assertSee($sales->name)
            ->assertSee('₦20,000.00')
            ->assertDontSee($other->name)
            ->assertDontSee('₦90,000.00');
    }

    public function test_manager_sees_all_sales_agents_with_status_counts(): void
    {
        $manager = User::factory()->manager()->create();
        $salesA = User::factory()->sales()->create();
        $salesB = User::factory()->sales()->create();

        $this->actingAs($manager);

        $this->officeSale($salesA, 'approved', 20000);
        $this->officeSale($salesA, 'pending', 5000);
        $this->officeSale($salesB, 'rejected', 3000);

        Livewire::test(OfficeSalesBreakdownTable::class)
            ->assertSee($salesA->name)
            ->assertSee($salesB->name)
            ->assertSee('₦20,000.00');
    }

    public function test_select_agent_shows_drilled_down_sales_records(): void
    {
        $accountant = User::factory()->accountant()->create();
        $sales = User::factory()->sales()->create(['name' => 'Grace Office Agent']);

        $this->actingAs($accountant);

        $this->officeSale($sales, 'approved', 10000, [
            'customer_name' => 'Alice Buyer',
            'products' => [
                [
                    'product_name' => 'Elkris Oat Flour',
                    'grammage' => '500g',
                    'quantity' => 2,
                    'price' => 5000,
                ],
            ],
        ]);

        Livewire::test(OfficeSalesBreakdownTable::class)
            ->call('selectAgent', $sales->id)
            ->assertSet('selectedAgentId', $sales->id)
            ->assertSee('Alice Buyer')
            ->assertSee('Elkris Oat Flour')
            ->assertSee('500g')
            ->assertSee('₦5,000.00')
            ->assertSee('₦10,000.00')
            ->call('backToSummary')
            ->assertSet('selectedAgentId', null)
            ->assertSee('Approved');
    }

    public function test_detail_records_respect_status_filter(): void
    {
        $manager = User::factory()->manager()->create();
        $sales = User::factory()->sales()->create();

        $this->actingAs($manager);

        $this->officeSale($sales, 'approved', 10000, ['customer_name' => 'Approved Customer']);
        $this->officeSale($sales, 'pending', 8000, ['customer_name' => 'Pending Customer']);

        Livewire::test(OfficeSalesBreakdownTable::class)
            ->call('selectAgent', $sales->id)
            ->assertSee('Approved Customer')
            ->assertSee('Pending Customer')
            ->set('statusFilter', 'pending')
            ->assertSee('Pending Customer')
            ->assertDontSee('Approved Customer');
    }

    public function test_summary_search_filters_by_agent_name(): void
    {
        $manager = User::factory()->manager()->create();
        $salesA = User::factory()->sales()->create(['name' => 'Ona Attah']);
        $salesB = User::factory()->sales()->create(['name' => 'Bola Musa']);

        $this->actingAs($manager);

        $this->officeSale($salesA, 'approved', 10000);
        $this->officeSale($salesB, 'approved', 20000);

        Livewire::test(OfficeSalesBreakdownTable::class)
            ->set('search', 'Ona')
            ->assertSee($salesA->name)
            ->assertDontSee($salesB->name);
    }

    public function test_summary_paginates_by_ten(): void
    {
        $manager = User::factory()->manager()->create();
        $this->actingAs($manager);

        $agents = collect(range(1, 12))->map(
            fn (int $i) => User::factory()->sales()->create(['name' => 'Agent '.chr(64 + $i)])
        );

        foreach ($agents as $i => $agent) {
            $this->officeSale($agent, 'approved', 1000 * ($i + 1));
        }

        Livewire::test(OfficeSalesBreakdownTable::class)
            ->assertSee('Agent L')
            ->assertSee('Agent C')
            ->assertDontSee('Agent A')
            ->assertDontSee('Agent B')
            ->call('setPage', 2)
            ->assertSee('Agent A')
            ->assertSee('Agent B')
            ->assertDontSee('Agent L');
    }

    public function test_detail_records_paginate_by_ten(): void
    {
        $manager = User::factory()->manager()->create();
        $sales = User::factory()->sales()->create(['name' => 'Paged Agent']);
        $this->actingAs($manager);

        collect(range(1, 12))->map(
            fn (int $i) => $this->officeSale($sales, 'approved', 1000, [
                'customer_name' => 'Paged Cust '.chr(64 + $i),
                'created_at' => now()->addMinutes($i),
            ])
        );

        Livewire::test(OfficeSalesBreakdownTable::class)
            ->call('selectAgent', $sales->id)
            ->assertSee('Paged Cust L')
            ->assertSee('Paged Cust C')
            ->assertDontSee('Paged Cust A')
            ->assertDontSee('Paged Cust B')
            ->call('setPage', 2)
            ->assertSee('Paged Cust A')
            ->assertSee('Paged Cust B')
            ->assertDontSee('Paged Cust L');
    }

    public function test_unauthorized_role_is_rejected(): void
    {
        $user = User::factory()->communitySalesRepresentative()->create();

        $this->actingAs($user);

        Livewire::test(OfficeSalesBreakdownTable::class)
            ->assertForbidden();
    }

    private function officeSale(User $user, string $status, float $total, array $attributes = []): SalesRecord
    {
        $customerName = $attributes['customer_name'] ?? 'Customer';

        return SalesRecord::factory()->create(array_merge([
            'agent_id' => $user->id,
            'agent_type' => 'sales',
            'status' => $status,
            'customer_name' => $customerName,
            'total_value' => $total,
            'products' => $attributes['products'] ?? [
                [
                    'product_name' => 'Elkris Oat Flour',
                    'grammage' => '100g',
                    'quantity' => 1,
                    'price' => $total,
                ],
            ],
        ], $attributes));
    }
}
