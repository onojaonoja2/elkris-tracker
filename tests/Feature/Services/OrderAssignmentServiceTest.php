<?php

namespace Tests\Feature\Services;

use App\Enums\AssignmentStatus;
use App\Enums\OrderStatus;
use App\Models\AgentStock;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\User;
use App\Services\OrderAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrderAssignmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private function createDeliverableOrder(User $processor, User $creator): Order
    {
        $productType = ProductType::factory()->create();

        $order = Order::factory()->create([
            'customer_id' => Customer::factory()->create()->id,
            'user_id' => $creator->id,
            'status' => OrderStatus::Assigned,
            'assignment_status' => AssignmentStatus::Accepted,
            'assigned_to' => $processor->id,
            'assigned_by' => User::factory()->admin()->create()->id,
            'assigned_at' => now(),
            'total_price' => 5000.00,
        ]);

        Product::create([
            'order_id' => $order->id,
            'product_type_id' => $productType->id,
            'product_name' => $productType->name,
            'grammage' => 100,
            'quantity' => 10,
            'price' => 500.00,
        ]);

        AgentStock::factory()->create([
            'user_id' => $processor->id,
            'product_type_id' => $productType->id,
            'product_name' => $productType->name,
            'grammage' => 100,
            'quantity' => 20,
        ]);

        return $order->refresh();
    }

    public function test_confirm_delivery_by_csr_requires_payment_proof(): void
    {
        $csr = User::factory()->communitySalesRepresentative()->create([
            'stock_balance' => 0.00,
        ]);
        $creator = User::factory()->rep()->create();
        $order = $this->createDeliverableOrder($csr, $creator);

        $this->expectException(ValidationException::class);
        OrderAssignmentService::confirmDeliveryByCsr($order);
    }

    public function test_confirm_delivery_by_csr_completes_after_payment_proof(): void
    {
        $csr = User::factory()->communitySalesRepresentative()->create([
            'stock_balance' => 0.00,
        ]);
        $creator = User::factory()->rep()->create();
        $order = $this->createDeliverableOrder($csr, $creator);

        $uploader = User::factory()->admin()->create();
        OrderAssignmentService::attachPaymentProof($order, 'proofs/order.jpg', $uploader->id);

        OrderAssignmentService::confirmDeliveryByCsr($order);

        $order->refresh();
        $this->assertEquals(OrderStatus::Delivered, $order->status);
        $this->assertEquals(AssignmentStatus::Delivered, $order->assignment_status);
        $this->assertEquals(5000.00, $csr->fresh()->stock_balance);
    }

    public function test_confirm_delivery_by_sales_requires_payment_proof(): void
    {
        $sales = User::factory()->sales()->create([
            'stock_balance' => 0.00,
        ]);
        $creator = User::factory()->rep()->create();
        $order = $this->createDeliverableOrder($sales, $creator);

        $this->expectException(ValidationException::class);
        OrderAssignmentService::confirmDeliveryBySales($order);
    }

    public function test_confirm_delivery_by_sales_completes_after_payment_proof(): void
    {
        $sales = User::factory()->sales()->create([
            'stock_balance' => 0.00,
        ]);
        $creator = User::factory()->rep()->create();
        $order = $this->createDeliverableOrder($sales, $creator);

        $uploader = User::factory()->admin()->create();
        OrderAssignmentService::attachPaymentProof($order, 'proofs/order.jpg', $uploader->id);

        OrderAssignmentService::confirmDeliveryBySales($order);

        $order->refresh();
        $this->assertEquals(OrderStatus::Delivered, $order->status);
        $this->assertEquals(AssignmentStatus::Delivered, $order->assignment_status);
        $this->assertEquals(5000.00, $creator->fresh()->stock_balance);
    }
}
