<?php

namespace Tests\Feature\Widgets;

use App\Enums\OrderStatus;
use App\Filament\Widgets\OrderStatsWidget;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrderStatsWidgetTest extends TestCase
{
    use RefreshDatabase;

    private function createOrder(User $user, float $price, OrderStatus $status): Order
    {
        return Order::factory()->create([
            'customer_id' => Customer::factory()->create()->id,
            'user_id' => $user->id,
            'status' => $status,
            'total_price' => $price,
            'is_migrated_order' => false,
        ]);
    }

    private function createAssignedOrder(User $submitter, User $csr, float $price, OrderStatus $status): Order
    {
        return Order::factory()->create([
            'customer_id' => Customer::factory()->create()->id,
            'user_id' => $submitter->id,
            'assigned_to' => $csr->id,
            'status' => $status,
            'total_price' => $price,
            'is_migrated_order' => false,
        ]);
    }

    public function test_agent_stats_renders_total_pending_and_delivered_values(): void
    {
        $user = User::factory()->sales()->create();
        $this->actingAs($user);

        $this->createOrder($user, 1000.00, OrderStatus::Delivered);
        $this->createOrder($user, 2000.00, OrderStatus::Pending);
        $this->createOrder($user, 500.00, OrderStatus::Delivered);

        Livewire::test(OrderStatsWidget::class)
            ->assertSee('My Total Orders')
            ->assertSee('Orders in selected range')
            ->assertSee('₦1,500')
            ->assertSee('My Pending Orders')
            ->assertSee('₦2,000')
            ->assertSee('My Delivered Orders')
            ->assertSee('₦1,500');
    }

    public function test_agent_stats_counts_dispatched_and_assigned_orders_as_pending(): void
    {
        $user = User::factory()->sales()->create();
        $this->actingAs($user);

        $this->createOrder($user, 50000.00, OrderStatus::Delivered);
        $this->createOrder($user, 20000.00, OrderStatus::Pending);
        $this->createOrder($user, 30000.00, OrderStatus::Dispatched);
        $this->createOrder($user, 5000.00, OrderStatus::Assigned);

        Livewire::test(OrderStatsWidget::class)
            ->assertSee('My Total Orders')
            ->assertSee('₦105,000')
            ->assertSee('My Pending Orders')
            ->assertSee('₦55,000')
            ->assertSee('My Delivered Orders')
            ->assertSee('₦50,000');
    }

    public function test_agent_stats_excludes_cancelled_orders_from_pending(): void
    {
        $user = User::factory()->sales()->create();
        $this->actingAs($user);

        $this->createOrder($user, 10000.00, OrderStatus::Pending);
        $this->createOrder($user, 4000.00, OrderStatus::Cancelled);

        Livewire::test(OrderStatsWidget::class)
            ->assertSee('My Total Orders')
            ->assertSee('₦14,000')
            ->assertSee('My Pending Orders')
            ->assertSee('₦10,000')
            ->assertDontSee('₦4,000');
    }

    public function test_agent_stats_shows_all_time_data_by_default(): void
    {
        $user = User::factory()->sales()->create();
        $this->actingAs($user);

        $this->createOrder($user, 25000.00, OrderStatus::Delivered)->update(['created_at' => now()->subDays(5)]);

        Livewire::test(OrderStatsWidget::class)
            ->assertSee('My Total Orders')
            ->assertSee('₦25,000')
            ->assertSee('My Pending Orders')
            ->assertSee('₦0')
            ->assertSee('My Delivered Orders')
            ->assertSee('₦25,000');
    }

    public function test_lead_stats_renders_personal_order_breakdown(): void
    {
        $lead = User::factory()->lead()->create();
        $this->actingAs($lead);

        $this->createOrder($lead, 50000.00, OrderStatus::Delivered);
        $this->createOrder($lead, 20000.00, OrderStatus::Pending);
        $this->createOrder($lead, 30000.00, OrderStatus::Dispatched);

        Livewire::test(OrderStatsWidget::class)
            ->assertSee('My Total Orders')
            ->assertSee('₦100,000')
            ->assertSee('My Pending Orders')
            ->assertSee('₦50,000')
            ->assertSee('My Delivered Orders')
            ->assertSee('₦50,000');
    }

    public function test_supervisor_stats_renders_csr_order_values(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $csr = User::factory()->communitySalesRepresentative()->create();
        $submitter = User::factory()->rep()->create();
        $this->actingAs($supervisor);

        $this->createAssignedOrder($submitter, $csr, 3000.00, OrderStatus::Pending);
        $this->createAssignedOrder($submitter, $csr, 2000.00, OrderStatus::Dispatched);
        $this->createAssignedOrder($submitter, $csr, 1500.00, OrderStatus::Delivered);

        Livewire::test(OrderStatsWidget::class)
            ->assertSee('CSR Orders Total')
            ->assertSee('₦6,500')
            ->assertSee('CSR Pending Orders')
            ->assertSee('₦5,000')
            ->assertSee('CSR Delivered Orders')
            ->assertSee('₦1,500');
    }

    public function test_management_stats_renders_total_pending_delivered_and_category_breakdowns(): void
    {
        $admin = User::factory()->admin()->create();
        $csr = User::factory()->communitySalesRepresentative()->create();
        $submitter = User::factory()->rep()->create();
        $openMarket = User::factory()->state(['role' => 'open_market'])->create();
        $retailMarket = User::factory()->state(['role' => 'retail_market'])->create();
        $this->actingAs($admin);

        $this->createAssignedOrder($submitter, $csr, 5000.00, OrderStatus::Pending);
        $this->createOrder($openMarket, 2500.00, OrderStatus::Delivered);
        $this->createOrder($retailMarket, 1500.00, OrderStatus::Pending);

        Livewire::test(OrderStatsWidget::class)
            ->assertSee('Total Orders Value')
            ->assertSee('₦9,000')
            ->assertSee('Pending Orders Value')
            ->assertSee('₦6,500')
            ->assertSee('Delivered Orders Value')
            ->assertSee('₦2,500')
            ->assertSee('CSR Orders')
            ->assertSee('₦5,000')
            ->assertSee('Open Market Orders')
            ->assertSee('₦2,500')
            ->assertSee('Retail Market Orders')
            ->assertSee('₦1,500');
    }

    public function test_supervisor_stats_renders_csr_completed_order_count(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $csr = User::factory()->communitySalesRepresentative()->create();
        $submitter = User::factory()->rep()->create();
        $this->actingAs($supervisor);

        foreach ([1000, 2000, 3000, 4000, 7000, 8000] as $price) {
            $this->createAssignedOrder($submitter, $csr, $price, OrderStatus::Delivered);
        }
        $this->createAssignedOrder($submitter, $csr, 100000.00, OrderStatus::Pending);

        Livewire::test(OrderStatsWidget::class)
            ->assertSee('CSR Completed Orders')
            ->assertSeeText('6');
    }

    public function test_management_stats_renders_csr_completed_order_count(): void
    {
        $admin = User::factory()->admin()->create();
        $csr = User::factory()->communitySalesRepresentative()->create();
        $submitter = User::factory()->rep()->create();
        $openMarket = User::factory()->state(['role' => 'open_market'])->create();
        $this->actingAs($admin);

        $this->createAssignedOrder($submitter, $csr, 1000.00, OrderStatus::Delivered);
        $this->createAssignedOrder($submitter, $csr, 2000.00, OrderStatus::Delivered);
        $this->createAssignedOrder($submitter, $csr, 4000.00, OrderStatus::Delivered);
        $this->createOrder($openMarket, 5000.00, OrderStatus::Delivered);

        Livewire::test(OrderStatsWidget::class)
            ->assertSee('CSR Completed Orders')
            ->assertSeeText('3');
    }

    public function test_migrated_orders_are_excluded(): void
    {
        $user = User::factory()->sales()->create();
        $this->actingAs($user);

        Order::factory()->create([
            'customer_id' => Customer::factory()->create()->id,
            'user_id' => $user->id,
            'status' => OrderStatus::Delivered,
            'total_price' => 9999.00,
            'is_migrated_order' => true,
        ]);

        $this->createOrder($user, 1000.00, OrderStatus::Delivered);

        Livewire::test(OrderStatsWidget::class)
            ->assertSee('₦1,000')
            ->assertDontSee('₦9,999');
    }

    public function test_supervisor_stats_ignores_csr_submitted_orders_that_are_not_assigned(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $csr = User::factory()->communitySalesRepresentative()->create();
        $this->actingAs($supervisor);

        $this->createOrder($csr, 9000.00, OrderStatus::Delivered);
        $this->createOrder($csr, 6000.00, OrderStatus::Pending);

        Livewire::test(OrderStatsWidget::class)
            ->assertSee('CSR Orders Total')
            ->assertSee('₦0')
            ->assertSee('CSR Completed Orders')
            ->assertSeeText('0');
    }

    public function test_sales_agent_stats_includes_orders_assigned_to_them(): void
    {
        $sales = User::factory()->sales()->create();
        $submitter = User::factory()->rep()->create();
        $this->actingAs($sales);

        $this->createAssignedOrder($submitter, $sales, 16000.00, OrderStatus::Delivered);
        $this->createAssignedOrder($submitter, $sales, 4000.00, OrderStatus::Pending);

        Livewire::test(OrderStatsWidget::class)
            ->assertSee('My Total Orders')
            ->assertSee('₦20,000')
            ->assertSee('My Pending Orders')
            ->assertSee('₦4,000')
            ->assertSee('My Delivered Orders')
            ->assertSee('₦16,000');
    }

    public function test_csr_agent_stats_uses_assigned_orders(): void
    {
        $csr = User::factory()->communitySalesRepresentative()->create();
        $submitter = User::factory()->rep()->create();
        $this->actingAs($csr);

        $this->createAssignedOrder($submitter, $csr, 4000.00, OrderStatus::Delivered);
        $this->createAssignedOrder($submitter, $csr, 6000.00, OrderStatus::Pending);
        $this->createOrder($submitter, 8000.00, OrderStatus::Delivered);

        Livewire::test(OrderStatsWidget::class)
            ->assertSee('My Total Orders')
            ->assertSee('₦10,000')
            ->assertSee('My Pending Orders')
            ->assertSee('₦6,000')
            ->assertSee('My Delivered Orders')
            ->assertSee('₦4,000');
    }
}
