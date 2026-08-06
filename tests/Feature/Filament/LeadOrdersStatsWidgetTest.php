<?php

namespace Tests\Feature\Filament;

use App\Enums\OrderStatus;
use App\Filament\Widgets\LeadOrdersStatsWidget;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use Tests\TestCase;

class LeadOrdersStatsWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_lead_sees_team_orders_within_selected_date_range(): void
    {
        $lead = User::factory()->lead()->create();
        $rep = User::factory()->rep()->create(['lead_id' => $lead->id]);
        $this->actingAs($lead);

        Session::put('dashboard_date_from', now()->startOfDay()->toDateTimeString());
        Session::put('dashboard_date_to', now()->endOfDay()->toDateTimeString());

        $this->createOrder($lead, 2000.00, OrderStatus::Delivered, now());
        $this->createOrder($rep, 3000.00, OrderStatus::Delivered, now());
        $this->createOrder($lead, 1500.00, OrderStatus::Pending, now()->subMonth());
        $this->createOrder($rep, 2500.00, OrderStatus::Delivered, now()->subMonth());

        Livewire::test(LeadOrdersStatsWidget::class)
            ->assertSee('My Orders')
            ->assertSee('Pending: ₦0.00 | Delivered: ₦2,000.00')
            ->assertSee('Rep Orders')
            ->assertSee('Pending: ₦0.00 | Delivered: ₦3,000.00')
            ->assertSee('Team Orders')
            ->assertSee('Pending: ₦0.00 | Delivered: ₦5,000.00')
            ->assertDontSee('₦1,500.00')
            ->assertDontSee('₦2,500.00');
    }

    public function test_lead_sees_all_orders_by_default_when_no_filter_is_set(): void
    {
        $lead = User::factory()->lead()->create();
        $rep = User::factory()->rep()->create(['lead_id' => $lead->id]);
        $this->actingAs($lead);

        $this->createOrder($lead, 2000.00, OrderStatus::Delivered, now());
        $this->createOrder($rep, 3000.00, OrderStatus::Delivered, now()->subMonth());

        Livewire::test(LeadOrdersStatsWidget::class)
            ->assertSee('My Orders')
            ->assertSee('Pending: ₦0.00 | Delivered: ₦2,000.00')
            ->assertSee('Rep Orders')
            ->assertSee('Pending: ₦0.00 | Delivered: ₦3,000.00')
            ->assertSee('Team Orders')
            ->assertSee('Pending: ₦0.00 | Delivered: ₦5,000.00');
    }

    public function test_pending_value_includes_undelivered_orders(): void
    {
        $lead = User::factory()->lead()->create();
        $rep = User::factory()->rep()->create(['lead_id' => $lead->id]);
        $this->actingAs($lead);

        $this->createOrder($lead, 2000.00, OrderStatus::Pending, now());
        $this->createOrder($lead, 4000.00, OrderStatus::Dispatched, now());
        $this->createOrder($rep, 3000.00, OrderStatus::Delivered, now());

        Livewire::test(LeadOrdersStatsWidget::class)
            ->assertSee('My Orders')
            ->assertSee('Pending: ₦6,000.00 | Delivered: ₦0.00')
            ->assertSee('Rep Orders')
            ->assertSee('Pending: ₦0.00 | Delivered: ₦3,000.00')
            ->assertSee('Team Orders')
            ->assertSee('Pending: ₦6,000.00 | Delivered: ₦3,000.00');
    }

    private function createOrder(User $user, float $price, OrderStatus $status, \DateTimeInterface $createdAt): Order
    {
        return Order::factory()->create([
            'customer_id' => Customer::factory()->create()->id,
            'user_id' => $user->id,
            'status' => $status,
            'total_price' => $price,
            'is_migrated_order' => false,
            'created_at' => $createdAt,
        ]);
    }
}
