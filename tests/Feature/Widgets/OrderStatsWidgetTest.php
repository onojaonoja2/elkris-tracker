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

    public function test_agent_stats_renders_total_pending_and_delivered_values(): void
    {
        $user = User::factory()->sales()->create();
        $this->actingAs($user);

        $this->createOrder($user, 1000.00, OrderStatus::Delivered);
        $this->createOrder($user, 2000.00, OrderStatus::Pending);
        $this->createOrder($user, 500.00, OrderStatus::Delivered);

        Livewire::test(OrderStatsWidget::class)
            ->assertSee('My Total Orders')
            ->assertSee('₦1,500')
            ->assertSee('My Pending Orders')
            ->assertSee('₦2,000')
            ->assertSee('My Delivered Orders')
            ->assertSee('₦1,500');
    }

    public function test_supervisor_stats_renders_csr_order_values(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $csr = User::factory()->communitySalesRepresentative()->create();
        $this->actingAs($supervisor);

        $this->createOrder($csr, 3000.00, OrderStatus::Pending);
        $this->createOrder($csr, 1500.00, OrderStatus::Delivered);

        Livewire::test(OrderStatsWidget::class)
            ->assertSee('CSR Orders Total')
            ->assertSee('₦4,500')
            ->assertSee('CSR Pending Orders')
            ->assertSee('₦3,000')
            ->assertSee('CSR Delivered Orders')
            ->assertSee('₦1,500');
    }

    public function test_management_stats_renders_total_pending_delivered_and_category_breakdowns(): void
    {
        $admin = User::factory()->admin()->create();
        $csr = User::factory()->communitySalesRepresentative()->create();
        $openMarket = User::factory()->state(['role' => 'open_market'])->create();
        $retailMarket = User::factory()->state(['role' => 'retail_market'])->create();
        $this->actingAs($admin);

        $this->createOrder($csr, 5000.00, OrderStatus::Pending);
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
}
