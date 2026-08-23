<?php

namespace Tests\Feature\Livewire;

use App\Enums\OrderStatus;
use App\Livewire\DashboardBreakdownTable;
use App\Models\Customer;
use App\Models\Order;
use App\Models\SalesRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardBreakdownTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_breakdown_includes_dispatched_and_assigned_orders(): void
    {
        $user = User::factory()->sales()->create();
        $this->actingAs($user);

        $customer = Customer::factory()->create();

        $pending = $this->createOrder($user, $customer, OrderStatus::Pending);
        $dispatched = $this->createOrder($user, $customer, OrderStatus::Dispatched);
        $assigned = $this->createOrder($user, $customer, OrderStatus::Assigned);
        $delivered = $this->createOrder($user, $customer, OrderStatus::Delivered);
        $cancelled = $this->createOrder($user, $customer, OrderStatus::Cancelled);

        Livewire::test(DashboardBreakdownTable::class, ['type' => 'order', 'category' => 'pending'])
            ->assertSee('#'.$pending->id)
            ->assertSee('#'.$dispatched->id)
            ->assertSee('#'.$assigned->id)
            ->assertDontSee('#'.$delivered->id)
            ->assertDontSee('#'.$cancelled->id);
    }

    public function test_delivered_breakdown_only_includes_delivered_orders(): void
    {
        $user = User::factory()->sales()->create();
        $this->actingAs($user);

        $customer = Customer::factory()->create();

        $delivered = $this->createOrder($user, $customer, OrderStatus::Delivered);
        $pending = $this->createOrder($user, $customer, OrderStatus::Pending);

        Livewire::test(DashboardBreakdownTable::class, ['type' => 'order', 'category' => 'delivered'])
            ->assertSee('#'.$delivered->id)
            ->assertDontSee('#'.$pending->id);
    }

    public function test_credit_breakdown_includes_all_outstanding_statuses(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $csr = User::factory()->communitySalesRepresentative()->create();

        $this->actingAs($supervisor);

        $this->creditRecord($csr, ['status' => 'receipt_uploaded', 'total_value' => 15000]);
        $this->creditRecord($csr, ['status' => 'approved', 'total_value' => 5000]);

        Livewire::test(DashboardBreakdownTable::class, ['type' => 'credit', 'category' => 'community_sales_representative'])
            ->assertSee('₦15,000.00')
            ->assertSee('₦5,000.00');
    }

    public function test_credit_breakdown_includes_null_credit_status_records(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $csr = User::factory()->communitySalesRepresentative()->create();

        $this->actingAs($supervisor);

        $this->creditRecord($csr, ['credit_status' => null, 'total_value' => 7000]);

        Livewire::test(DashboardBreakdownTable::class, ['type' => 'credit', 'category' => 'community_sales_representative'])
            ->assertSee('₦7,000.00');
    }

    public function test_credit_breakdown_filters_by_pending_payment(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $csr = User::factory()->communitySalesRepresentative()->create();

        $this->actingAs($supervisor);

        $this->creditRecord($csr, ['credit_status' => 'pending_payment', 'total_value' => 10000]);
        $this->creditRecord($csr, ['credit_status' => 'partially_collected', 'total_value' => 8000]);
        $this->creditRecord($csr, ['credit_status' => null, 'total_value' => 6000]);

        Livewire::test(DashboardBreakdownTable::class, ['type' => 'credit', 'category' => 'community_sales_representative'])
            ->set('statusFilter', 'pending_payment')
            ->assertSee('₦10,000.00')
            ->assertDontSee('₦8,000.00')
            ->assertDontSee('₦6,000.00');
    }

    public function test_credit_breakdown_filters_by_overdue(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $csr = User::factory()->communitySalesRepresentative()->create();

        $this->actingAs($supervisor);

        $this->creditRecord($csr, ['total_value' => 9000, 'expected_collection_date' => now()->subDays(2)->toDateString()]);
        $this->creditRecord($csr, ['total_value' => 4000, 'expected_collection_date' => now()->addDays(5)->toDateString()]);

        Livewire::test(DashboardBreakdownTable::class, ['type' => 'credit', 'category' => 'community_sales_representative'])
            ->assertSee('₦9,000.00')
            ->assertSee('₦4,000.00')
            ->set('statusFilter', 'overdue')
            ->assertSee('₦9,000.00')
            ->assertDontSee('₦4,000.00');
    }

    public function test_supervisor_total_credit_breakdown_includes_all_channels(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $csr = User::factory()->communitySalesRepresentative()->create();
        $retail = User::factory()->create(['role' => 'retail_market']);

        $this->actingAs($supervisor);

        $this->creditRecord($csr, ['total_value' => 15000, 'status' => 'receipt_uploaded']);
        $this->creditRecord($csr, ['total_value' => 5000]);
        $this->creditRecord($retail, ['total_value' => 7000], 'retail_market');

        Livewire::test(DashboardBreakdownTable::class, ['type' => 'credit', 'category' => 'total'])
            ->assertSee('₦15,000.00')
            ->assertSee('₦5,000.00')
            ->assertSee('₦7,000.00');
    }

    public function test_supervisor_credit_breakdown_scopes_to_csr_channel(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $csr = User::factory()->communitySalesRepresentative()->create();
        $retail = User::factory()->create(['role' => 'retail_market']);

        $this->actingAs($supervisor);

        $this->creditRecord($csr, ['total_value' => 15000, 'status' => 'receipt_uploaded']);
        $this->creditRecord($retail, ['total_value' => 7000], 'retail_market');

        Livewire::test(DashboardBreakdownTable::class, ['type' => 'credit', 'category' => 'community_sales_representative'])
            ->assertSee('₦15,000.00')
            ->assertDontSee('₦7,000.00');
    }

    public function test_manager_credit_channel_breakdown_uses_agent_type(): void
    {
        $manager = User::factory()->manager()->create();
        $csr = User::factory()->communitySalesRepresentative()->create();
        $retail = User::factory()->create(['role' => 'retail_market']);

        $this->actingAs($manager);

        $this->creditRecord($csr, ['total_value' => 15000, 'status' => 'receipt_uploaded']);
        $this->creditRecord($retail, ['total_value' => 7000], 'retail_market');

        Livewire::test(DashboardBreakdownTable::class, ['type' => 'credit', 'category' => 'retail_market'])
            ->assertSee('₦7,000.00')
            ->assertDontSee('₦15,000.00');
    }

    public function test_agent_credit_overdue_breakdown_filters_by_expected_date(): void
    {
        $csr = User::factory()->communitySalesRepresentative()->create();
        $this->actingAs($csr);

        $this->creditRecord($csr, ['total_value' => 9000, 'expected_collection_date' => now()->subDays(2)->toDateString()]);
        $this->creditRecord($csr, ['total_value' => 4000, 'expected_collection_date' => now()->addDays(5)->toDateString()]);

        Livewire::test(DashboardBreakdownTable::class, ['type' => 'credit', 'category' => 'overdue'])
            ->assertSee('₦9,000.00')
            ->assertDontSee('₦4,000.00');
    }

    public function test_supervisor_total_order_breakdown_includes_all_channels(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $sales = User::factory()->sales()->create();
        $retail = User::factory()->create(['role' => 'retail_market']);
        $customer = Customer::factory()->create();

        $this->actingAs($supervisor);

        $salesOrder = $this->createOrder($sales, $customer, OrderStatus::Pending);
        $retailOrder = $this->createOrder($retail, $customer, OrderStatus::Pending);

        Livewire::test(DashboardBreakdownTable::class, ['type' => 'order', 'category' => 'total'])
            ->assertSee('#'.$salesOrder->id)
            ->assertSee('#'.$retailOrder->id);
    }

    public function test_supervisor_pending_breakdown_scopes_to_csr_orders(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $csr = User::factory()->communitySalesRepresentative()->create();
        $sales = User::factory()->sales()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($supervisor);

        $csrOrder = $this->createAssignedOrder($sales, $csr, $customer, OrderStatus::Pending);
        $salesOrder = $this->createOrder($sales, $customer, OrderStatus::Pending);

        Livewire::test(DashboardBreakdownTable::class, ['type' => 'order', 'category' => 'pending'])
            ->assertSee('#'.$csrOrder->id)
            ->assertDontSee('#'.$salesOrder->id);
    }

    public function test_supervisor_delivered_breakdown_scopes_to_csr_orders(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $csr = User::factory()->communitySalesRepresentative()->create();
        $sales = User::factory()->sales()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($supervisor);

        $csrOrder = $this->createAssignedOrder($sales, $csr, $customer, OrderStatus::Delivered);
        $salesOrder = $this->createOrder($sales, $customer, OrderStatus::Delivered);

        Livewire::test(DashboardBreakdownTable::class, ['type' => 'order', 'category' => 'delivered'])
            ->assertSee('#'.$csrOrder->id)
            ->assertDontSee('#'.$salesOrder->id);
    }

    public function test_supervisor_breakdown_ignores_csr_submitted_orders_that_are_not_assigned(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $csr = User::factory()->communitySalesRepresentative()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($supervisor);

        $unassignedCsrOrder = $this->createOrder($csr, $customer, OrderStatus::Pending);

        Livewire::test(DashboardBreakdownTable::class, ['type' => 'order', 'category' => 'pending'])
            ->assertDontSee('#'.$unassignedCsrOrder->id);
    }

    public function test_order_breakdown_paginates_by_ten(): void
    {
        $user = User::factory()->sales()->create();
        $this->actingAs($user);

        $customer = Customer::factory()->create();

        $orders = collect(range(1, 12))->map(
            fn (int $i) => $this->createOrder($user, $customer, OrderStatus::Pending, ['created_at' => now()->addMinutes($i)])
        );

        session()->put('dashboard_date_from', now()->subDay()->toDateTimeString());
        session()->put('dashboard_date_to', now()->addDay()->toDateTimeString());

        Livewire::test(DashboardBreakdownTable::class, ['type' => 'order', 'category' => 'pending'])
            ->assertSee('>#'.$orders[11]->id.'<', false)
            ->assertSee('>#'.$orders[2]->id.'<', false)
            ->assertDontSee('>#'.$orders[0]->id.'<', false)
            ->assertDontSee('>#'.$orders[1]->id.'<', false)
            ->call('setPage', 2)
            ->assertSee('>#'.$orders[0]->id.'<', false)
            ->assertSee('>#'.$orders[1]->id.'<', false)
            ->assertDontSee('>#'.$orders[11]->id.'<', false);
    }

    public function test_supervisor_order_breakdown_shows_assigned_csr(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $csr = User::factory()->communitySalesRepresentative()->create(['name' => 'Assigned CSR Name']);
        $sales = User::factory()->sales()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($supervisor);

        $assignedOrder = $this->createAssignedOrder($sales, $csr, $customer, OrderStatus::Delivered);

        Livewire::test(DashboardBreakdownTable::class, ['type' => 'order', 'category' => 'delivered'])
            ->assertSee('#'.$assignedOrder->id)
            ->assertSee('Assigned CSR Name');
    }

    private function creditRecord(User $user, array $attributes = [], ?string $agentType = null): SalesRecord
    {
        return SalesRecord::factory()->create(array_merge([
            'agent_id' => $user->id,
            'agent_type' => $agentType ?? 'community_sales_representative',
            'status' => 'approved',
            'is_credit' => true,
            'credit_status' => 'pending_payment',
            'expected_collection_date' => now()->addDays(7)->toDateString(),
            'total_value' => 10000,
        ], $attributes));
    }

    private function createOrder(User $user, Customer $customer, OrderStatus $status, array $attributes = []): Order
    {
        return Order::factory()->create(array_merge([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'status' => $status,
            'total_price' => 1000.00,
            'is_migrated_order' => false,
        ], $attributes));
    }

    private function createAssignedOrder(User $submitter, User $csr, Customer $customer, OrderStatus $status): Order
    {
        return Order::factory()->create([
            'customer_id' => $customer->id,
            'user_id' => $submitter->id,
            'assigned_to' => $csr->id,
            'status' => $status,
            'total_price' => 1000.00,
            'is_migrated_order' => false,
        ]);
    }
}
