<?php

namespace Tests\Feature\Services;

use App\Enums\AssignmentStatus;
use App\Enums\OrderStatus;
use App\Models\AgentStock;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\User;
use App\Services\OrderAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrderDeliveryFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_mark_delivered_routes_csr_assigned_order_through_csr_flow(): void
    {
        $sales = User::factory()->sales()->create();
        $csr = User::factory()->communitySalesRepresentative()->create();

        $order = $this->createOrderWithStock($csr, [
            'user_id' => $sales->id,
            'assigned_to' => $csr->id,
            'assigned_by' => $sales->id,
            'assignment_status' => AssignmentStatus::Accepted,
            'status' => OrderStatus::Assigned,
            'total_price' => 2000,
        ]);
        $this->attachProof($order, $sales);

        OrderAssignmentService::markOrderDelivered($order);

        $order->refresh();
        $this->assertEquals(OrderStatus::Delivered, $order->status);
        $this->assertEquals(AssignmentStatus::Delivered, $order->assignment_status);
        $this->assertSame(8, AgentStock::where('user_id', $csr->id)->first()->quantity);
        $this->assertSame(2000.0, (float) $csr->fresh()->stock_balance);
    }

    public function test_mark_delivered_requires_payment_proof(): void
    {
        $csr = User::factory()->communitySalesRepresentative()->create();
        $order = $this->createOrderWithStock($csr, [
            'assigned_to' => $csr->id,
            'assignment_status' => AssignmentStatus::Accepted,
        ]);

        try {
            OrderAssignmentService::markOrderDelivered($order);
            $this->fail('Expected a ValidationException for missing payment proof.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('payment_proof', $e->errors());
        }
    }

    public function test_mark_delivered_for_unassigned_order_requires_sales_handler(): void
    {
        $sales = User::factory()->sales()->create();
        $order = $this->createOrderWithStock($sales);
        $this->attachProof($order, $sales);

        try {
            OrderAssignmentService::markOrderDelivered($order);
            $this->fail('Expected a ValidationException for missing sales handler.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('delivered_by_sales', $e->errors());
        }
    }

    public function test_mark_delivered_for_unassigned_order_assigns_sales_handler(): void
    {
        $rep = User::factory()->rep()->create();
        $handler = User::factory()->sales()->create();

        $order = $this->createOrderWithStock($handler, [
            'user_id' => $rep->id,
            'total_price' => 2000,
        ]);
        $this->attachProof($order, $rep);

        OrderAssignmentService::markOrderDelivered($order, $handler, $rep->id);

        $order->refresh();
        $this->assertSame($handler->id, $order->assigned_to);
        $this->assertSame($rep->id, $order->assigned_by);
        $this->assertEquals(AssignmentStatus::Delivered, $order->assignment_status);
        $this->assertEquals(OrderStatus::Delivered, $order->status);
        $this->assertSame(8, AgentStock::where('user_id', $handler->id)->first()->quantity);
        $this->assertSame(2000.0, (float) $rep->fresh()->stock_balance);
    }

    public function test_mark_delivered_rejects_non_sales_handler(): void
    {
        $rep = User::factory()->rep()->create();
        $otherRep = User::factory()->rep()->create();

        $order = $this->createOrderWithStock($rep, ['user_id' => $rep->id]);
        $this->attachProof($order, $rep);

        try {
            OrderAssignmentService::markOrderDelivered($order, $otherRep, $rep->id);
            $this->fail('Expected a ValidationException for a non-sales handler.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('delivered_by_sales', $e->errors());
        }
    }

    public function test_mark_delivered_throws_for_fully_delivered_order(): void
    {
        $sales = User::factory()->sales()->create();
        $order = $this->createOrderWithStock($sales, [
            'status' => OrderStatus::Delivered,
            'assignment_status' => AssignmentStatus::Delivered,
        ]);
        $this->attachProof($order, $sales);

        $this->expectException(ValidationException::class);

        OrderAssignmentService::markOrderDelivered($order);
    }

    public function test_replace_payment_proof_swaps_file_and_removes_old(): void
    {
        Storage::fake('s3');

        $sales = User::factory()->sales()->create();
        $order = Order::factory()->create(['user_id' => $sales->id]);
        Storage::disk('s3')->put('receipts/payment-proofs/old.png', 'old-content');
        $this->attachProof($order, $sales, 'receipts/payment-proofs/old.png');

        OrderAssignmentService::replacePaymentProof($order, 'receipts/payment-proofs/new.png', $sales->id);

        $order->refresh();
        $this->assertSame('receipts/payment-proofs/new.png', $order->payment_proof_path);
        $this->assertSame($sales->id, $order->payment_proof_uploaded_by);
        Storage::disk('s3')->assertMissing('receipts/payment-proofs/old.png');
    }

    public function test_replace_payment_proof_requires_existing_proof(): void
    {
        $sales = User::factory()->sales()->create();
        $order = Order::factory()->create(['user_id' => $sales->id]);

        $this->expectException(ValidationException::class);

        OrderAssignmentService::replacePaymentProof($order, 'receipts/payment-proofs/new.png', $sales->id);
    }

    public function test_complete_delivered_edit_syncs_out_of_sync_order(): void
    {
        $sales = User::factory()->sales()->create();
        $csr = User::factory()->communitySalesRepresentative()->create();

        $order = $this->createOrderWithStock($csr, [
            'user_id' => $sales->id,
            'assigned_to' => $csr->id,
            'assignment_status' => AssignmentStatus::Accepted,
            'status' => OrderStatus::Delivered,
            'total_price' => 2000,
        ]);
        $this->attachProof($order, $sales);

        OrderAssignmentService::completeDeliveredEdit($order, null);

        $order->refresh();
        $this->assertEquals(AssignmentStatus::Delivered, $order->assignment_status);
        $this->assertSame(8, AgentStock::where('user_id', $csr->id)->first()->quantity);
    }

    public function test_complete_delivered_edit_ignores_migrated_synced_and_pending_orders(): void
    {
        $sales = User::factory()->sales()->create();

        $migrated = Order::factory()->create([
            'user_id' => $sales->id,
            'status' => OrderStatus::Delivered,
            'assignment_status' => AssignmentStatus::None,
            'is_migrated_order' => true,
        ]);

        // No proof and no stock: must be a silent no-op, not an exception.
        OrderAssignmentService::completeDeliveredEdit($migrated, null);
        $this->assertEquals(AssignmentStatus::None, $migrated->fresh()->assignment_status);

        $synced = $this->createOrderWithStock($sales, [
            'status' => OrderStatus::Delivered,
            'assignment_status' => AssignmentStatus::Delivered,
        ]);
        OrderAssignmentService::completeDeliveredEdit($synced, null);
        $this->assertEquals(AssignmentStatus::Delivered, $synced->fresh()->assignment_status);

        $pending = Order::factory()->create(['user_id' => $sales->id]);
        OrderAssignmentService::completeDeliveredEdit($pending, null);
        $this->assertEquals(OrderStatus::Pending, $pending->fresh()->status);
    }

    private function createOrderWithStock(User $holder, array $orderAttributes = []): Order
    {
        $productType = ProductType::factory()->create(['name' => fake()->unique()->word()]);

        $order = Order::factory()->create(array_merge([
            'total_price' => 2000,
            'is_migrated_order' => false,
        ], $orderAttributes));

        Product::create([
            'order_id' => $order->id,
            'product_type_id' => $productType->id,
            'product_name' => $productType->name,
            'grammage' => 100,
            'quantity' => 2,
            'price' => 1000,
        ]);

        AgentStock::create([
            'user_id' => $holder->id,
            'product_type_id' => $productType->id,
            'product_name' => $productType->name,
            'grammage' => 100,
            'quantity' => 10,
        ]);

        return $order->fresh();
    }

    private function attachProof(Order $order, User $uploader, string $path = 'receipts/payment-proofs/proof.png'): Order
    {
        $order->update([
            'payment_proof_path' => $path,
            'payment_proof_uploaded_by' => $uploader->id,
            'payment_proof_uploaded_at' => now(),
        ]);

        return $order->fresh();
    }
}
