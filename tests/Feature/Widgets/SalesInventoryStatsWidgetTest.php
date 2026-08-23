<?php

namespace Tests\Feature\Widgets;

use App\Enums\OrderStatus;
use App\Filament\Widgets\SalesInventoryStatsWidget;
use App\Models\AgentStock;
use App\Models\Customer;
use App\Models\Order;
use App\Models\ProductType;
use App\Models\SalesRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use Tests\TestCase;

class SalesInventoryStatsWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_all_time_data_by_default(): void
    {
        $sales = User::factory()->sales()->create();
        $this->actingAs($sales);

        $this->createPastWeekApprovedSales($sales);
        $this->createDeliveredOrder($sales, 25000.00, now()->subDays(5));
        $this->createStockOnHand($sales);

        Livewire::test(SalesInventoryStatsWidget::class)
            ->assertSee('Total Stock Units')
            ->assertSee('131')
            ->assertSee('Products')
            ->assertSee('4')
            ->assertSee('Sales')
            ->assertSee('6')
            ->assertSee('₦120,000.00')
            ->assertSee('Total Orders')
            ->assertSee('Pending: 0 | Delivered: 1')
            ->assertSee('Unassigned Orders')
            ->assertSee('Pending Requests')
            ->assertSee('Pending Returns');
    }

    public function test_respects_selected_date_range(): void
    {
        $sales = User::factory()->sales()->create();
        $this->actingAs($sales);

        Session::put('dashboard_date_from', now()->startOfDay()->toDateTimeString());
        Session::put('dashboard_date_to', now()->endOfDay()->toDateTimeString());

        $this->createPastWeekApprovedSales($sales);
        SalesRecord::factory()->approved()->create([
            'agent_id' => $sales->id,
            'total_value' => 5000,
            'is_credit' => false,
            'created_at' => now(),
        ]);
        $this->createDeliveredOrder($sales, 25000.00, now()->subDays(5));
        $this->createOrder($sales, 3000.00, OrderStatus::Pending, now());
        $this->createStockOnHand($sales);

        Livewire::test(SalesInventoryStatsWidget::class)
            ->assertSee('Sales')
            ->assertSee('1')
            ->assertSee('₦5,000.00')
            ->assertDontSee('₦120,000.00')
            ->assertSee('Total Orders')
            ->assertSee('Pending: 1 | Delivered: 0')
            ->assertSee('131');
    }

    public function test_sales_user_total_orders_includes_assigned_orders(): void
    {
        $sales = User::factory()->sales()->create();
        $this->actingAs($sales);

        $submitter = User::factory()->rep()->create();

        Order::factory()->create([
            'customer_id' => Customer::factory()->create()->id,
            'user_id' => $submitter->id,
            'assigned_to' => $sales->id,
            'status' => OrderStatus::Delivered,
            'total_price' => 16000.00,
            'is_migrated_order' => false,
            'created_at' => now(),
        ]);

        Livewire::test(SalesInventoryStatsWidget::class)
            ->assertSee('Total Orders')
            ->assertSee('Pending: 0 | Delivered: 1');
    }

    private function createPastWeekApprovedSales(User $user): void
    {
        foreach ([1, 2, 3, 4, 5, 6] as $day) {
            SalesRecord::factory()->approved()->create([
                'agent_id' => $user->id,
                'total_value' => 20000,
                'is_credit' => false,
                'created_at' => now()->subDays($day),
            ]);
        }
    }

    private function createDeliveredOrder(User $user, float $price, \DateTimeInterface $createdAt): Order
    {
        return $this->createOrder($user, $price, OrderStatus::Delivered, $createdAt, $user->id);
    }

    private function createOrder(User $user, float $price, OrderStatus $status, \DateTimeInterface $createdAt, ?int $assignedTo = null): Order
    {
        return Order::factory()->create([
            'customer_id' => Customer::factory()->create()->id,
            'user_id' => $user->id,
            'status' => $status,
            'total_price' => $price,
            'is_migrated_order' => false,
            'assigned_to' => $assignedTo,
            'created_at' => $createdAt,
        ]);
    }

    private function createStockOnHand(User $user): void
    {
        $products = [
            ['Elkris Oat Flour', 66],
            ['Elkris Plantain Flour', 25],
            ['Elkris Poundo Yam', 20],
            ['Oat & Fiber Flour', 20],
        ];

        foreach ($products as [$name, $quantity]) {
            AgentStock::create([
                'user_id' => $user->id,
                'product_type_id' => ProductType::factory()->create(['name' => $name])->id,
                'product_name' => $name,
                'grammage' => 100,
                'quantity' => $quantity,
            ]);
        }
    }
}
