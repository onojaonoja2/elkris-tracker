<?php

namespace Tests\Feature\Widgets;

use App\Enums\AssignmentStatus;
use App\Enums\OrderStatus;
use App\Filament\Widgets\CsrAssignedOrdersWidget;
use App\Models\AgentStock;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\User;
use App\Services\OrderAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CsrAssignedOrdersWidgetTest extends TestCase
{
    use RefreshDatabase;

    private static int $productTypeCounter = 0;

    private function makeAssignedOrder(User $csr, User $creator): Order
    {
        $productType = ProductType::factory()->create([
            'name' => 'Elkris Plantain Flour '.++self::$productTypeCounter,
            'available_grammages' => [100, 200],
        ]);

        $order = Order::factory()->create([
            'customer_id' => Customer::factory()->create()->id,
            'user_id' => $creator->id,
            'status' => OrderStatus::Assigned,
            'assignment_status' => AssignmentStatus::Assigned,
            'assigned_to' => $csr->id,
            'assigned_by' => User::factory()->admin()->create()->id,
            'assigned_at' => now(),
            'total_price' => 5500.00,
        ]);

        Product::create([
            'order_id' => $order->id,
            'product_type_id' => $productType->id,
            'product_name' => $productType->name,
            'grammage' => 100,
            'quantity' => 10,
            'price' => 500.00,
        ]);

        Product::create([
            'order_id' => $order->id,
            'product_type_id' => $productType->id,
            'product_name' => $productType->name,
            'grammage' => 200,
            'quantity' => 1,
            'price' => 500.00,
        ]);

        return $order->refresh();
    }

    public function test_csr_sees_only_orders_assigned_to_them(): void
    {
        $csr = User::factory()->communitySalesRepresentative()->create();
        $otherCsr = User::factory()->communitySalesRepresentative()->create();
        $creator = User::factory()->rep()->create();

        $myOrder = $this->makeAssignedOrder($csr, $creator);
        $otherOrder = $this->makeAssignedOrder($otherCsr, $creator);

        $this->actingAs($csr);

        Livewire::test(CsrAssignedOrdersWidget::class)
            ->assertCanSeeTableRecords([$myOrder])
            ->assertCanNotSeeTableRecords([$otherOrder]);
    }

    public function test_csr_can_view_assigned_order_with_customer_and_item_details(): void
    {
        $csr = User::factory()->communitySalesRepresentative()->create();
        $creator = User::factory()->rep()->create();
        $order = $this->makeAssignedOrder($csr, $creator);

        $customer = $order->customer;

        $this->actingAs($csr);

        Livewire::test(CsrAssignedOrdersWidget::class)
            ->mountTableAction('viewOrder', $order)
            ->assertMountedActionModalSee([
                $customer->customer_name,
                $customer->phone_number,
                $order->products->first()->product_name,
                '100g',
                '10',
                '500.00',
                '5,000.00',
                '5,500.00',
            ]);
    }

    public function test_csr_can_confirm_delivery_when_stock_matches_by_product_type(): void
    {
        $csr = User::factory()->communitySalesRepresentative()->create();
        $creator = User::factory()->rep()->create();
        $productType = ProductType::factory()->create(['name' => 'Elkris Plantain Flour']);

        $order = Order::factory()->create([
            'customer_id' => Customer::factory()->create()->id,
            'user_id' => $creator->id,
            'status' => OrderStatus::Assigned,
            'assignment_status' => AssignmentStatus::Accepted,
            'assigned_to' => $csr->id,
            'assigned_by' => User::factory()->admin()->create()->id,
            'assigned_at' => now(),
            'total_price' => 15000.00,
        ]);

        Product::create([
            'order_id' => $order->id,
            'product_type_id' => $productType->id,
            'product_name' => 'Elkris Plantain',
            'grammage' => 1800,
            'quantity' => 6,
            'price' => 2500.00,
        ]);

        AgentStock::factory()->create([
            'user_id' => $csr->id,
            'product_type_id' => $productType->id,
            'product_name' => 'Elkris Plantain Flour',
            'grammage' => 1800,
            'quantity' => 45,
        ]);

        OrderAssignmentService::attachPaymentProof($order, 'proofs/order.jpg', User::factory()->admin()->create()->id);

        $this->actingAs($csr);

        Livewire::test(CsrAssignedOrdersWidget::class)
            ->callTableAction('confirmDelivery', $order->id)
            ->assertNotified()
            ->assertDispatched('refresh-dashboard');

        $this->assertEquals(OrderStatus::Delivered, $order->fresh()->status);
        $this->assertEquals(39, AgentStock::first()->fresh()->quantity);
    }

    public function test_csr_confirm_delivery_shows_insufficient_stock_notification(): void
    {
        $csr = User::factory()->communitySalesRepresentative()->create();
        $creator = User::factory()->rep()->create();
        $productType = ProductType::factory()->create(['name' => 'Elkris Plantain Flour']);

        $order = Order::factory()->create([
            'customer_id' => Customer::factory()->create()->id,
            'user_id' => $creator->id,
            'status' => OrderStatus::Assigned,
            'assignment_status' => AssignmentStatus::Accepted,
            'assigned_to' => $csr->id,
            'assigned_by' => User::factory()->admin()->create()->id,
            'assigned_at' => now(),
            'total_price' => 15000.00,
        ]);

        Product::create([
            'order_id' => $order->id,
            'product_type_id' => $productType->id,
            'product_name' => 'Elkris Plantain',
            'grammage' => 1800,
            'quantity' => 6,
            'price' => 2500.00,
        ]);

        AgentStock::factory()->create([
            'user_id' => $csr->id,
            'product_type_id' => $productType->id,
            'product_name' => 'Elkris Plantain Flour',
            'grammage' => 1800,
            'quantity' => 3,
        ]);

        OrderAssignmentService::attachPaymentProof($order, 'proofs/order.jpg', User::factory()->admin()->create()->id);

        $this->actingAs($csr);

        Livewire::test(CsrAssignedOrdersWidget::class)
            ->callTableAction('confirmDelivery', $order->id)
            ->assertNotified('Insufficient stock');

        $this->assertEquals(OrderStatus::Assigned, $order->fresh()->status);
        $this->assertEquals(3, AgentStock::first()->fresh()->quantity);
    }
}
