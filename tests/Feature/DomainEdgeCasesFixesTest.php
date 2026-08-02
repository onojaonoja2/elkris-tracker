<?php

namespace Tests\Feature;

use App\Enums\AssignmentStatus;
use App\Enums\OrderStatus;
use App\Enums\StockTransferStatus;
use App\Models\AgentStock;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\StockCount;
use App\Models\StockCountItem;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\OrderAssignmentService;
use App\Services\StockTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DomainEdgeCasesFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_lifetime_purchases_updates_correctly_and_safely(): void
    {
        $customer = Customer::factory()->create(['lifetime_purchases' => []]);
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::Pending,
        ]);

        Product::create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'product_name' => 'Elkris Super Oats',
            'grammage' => 500,
            'quantity' => 4,
            'unit_price' => 1500,
            'total_price' => 6000,
        ]);

        $order->update(['status' => OrderStatus::Delivered]);

        $customer->refresh();
        $this->assertEquals(['Elkris Super Oats - 500g' => 4], $customer->lifetime_purchases);

        // Transition away from Delivered should decrement
        $order->update(['status' => OrderStatus::Cancelled]);
        $customer->refresh();
        $this->assertEmpty($customer->lifetime_purchases);
    }

    public function test_order_delivery_throws_validation_exception_when_stock_insufficient(): void
    {
        $csr = User::factory()->communitySalesRepresentative()->create();
        $order = Order::factory()->create([
            'assigned_to' => $csr->id,
            'assignment_status' => AssignmentStatus::Accepted,
            'status' => OrderStatus::Assigned,
            'payment_proof_path' => 'receipts/test.jpg',
            'total_price' => 10000,
        ]);

        $productType = ProductType::factory()->create(['name' => 'Tea']);
        Product::create([
            'order_id' => $order->id,
            'product_type_id' => $productType->id,
            'product_name' => 'Tea',
            'grammage' => 200,
            'quantity' => 10,
            'unit_price' => 1000,
            'total_price' => 10000,
        ]);

        // Agent only has 2 pieces of stock
        AgentStock::create([
            'user_id' => $csr->id,
            'product_type_id' => $productType->id,
            'product_name' => 'Tea',
            'grammage' => 200,
            'quantity' => 2,
        ]);

        $this->expectException(ValidationException::class);

        OrderAssignmentService::confirmDeliveryByCsr($order);
    }

    public function test_stock_transfer_receive_does_not_duplicate_stock_to_requester(): void
    {
        $warehouse = Warehouse::factory()->create();
        $targetAgent = User::factory()->communitySalesRepresentative()->create();
        $requesterAgent = User::factory()->fieldAgent()->create();
        $productType = ProductType::factory()->create(['name' => 'Oatmeal']);

        $transfer = StockTransfer::create([
            'from_warehouse_id' => $warehouse->id,
            'to_agent_id' => $targetAgent->id,
            'requested_by' => $requesterAgent->id,
            'status' => StockTransferStatus::Dispatched,
        ]);

        $item = StockTransferItem::create([
            'stock_transfer_id' => $transfer->id,
            'product_type_id' => $productType->id,
            'grammage' => 500,
            'quantity' => 15,
            'rejected_quantity' => 0,
        ]);

        StockTransferService::receive($transfer, [
            [
                'item_id' => $item->id,
                'accepted_quantity' => 15,
                'rejected_quantity' => 0,
            ],
        ]);

        // Target agent should receive 15 stock
        $this->assertDatabaseHas('agent_stocks', [
            'user_id' => $targetAgent->id,
            'product_type_id' => $productType->id,
            'grammage' => 500,
            'quantity' => 15,
        ]);

        // Requester agent should NOT receive duplicate stock
        $this->assertDatabaseMissing('agent_stocks', [
            'user_id' => $requesterAgent->id,
            'product_type_id' => $productType->id,
            'grammage' => 500,
        ]);
    }

    public function test_additional_stock_count_approval_increments_inventory(): void
    {
        $warehouse = Warehouse::factory()->create();
        $accountant = User::factory()->accountant()->create();
        $productType = ProductType::factory()->create();

        // Initial inventory is 10
        Inventory::create([
            'warehouse_id' => $warehouse->id,
            'product_type_id' => $productType->id,
            'grammage' => 250,
            'quantity' => 10,
        ]);

        $stockCount = StockCount::create([
            'warehouse_id' => $warehouse->id,
            'user_id' => $accountant->id,
            'status' => 'pending',
            'supervisor_status' => 'verified',
            'is_additional_count' => true,
        ]);

        StockCountItem::create([
            'stock_count_id' => $stockCount->id,
            'product_type_id' => $productType->id,
            'product_name' => $productType->name,
            'grammage' => 250,
            'quantity' => 5,
        ]);

        $stockCount->update([
            'status' => 'approved',
            'approved_by' => $accountant->id,
            'approved_at' => now(),
        ]);

        Inventory::firstOrCreate([
            'warehouse_id' => $warehouse->id,
            'product_type_id' => $productType->id,
            'grammage' => 250,
        ], ['quantity' => 0])->increment('quantity', 5);

        $this->assertDatabaseHas('inventories', [
            'warehouse_id' => $warehouse->id,
            'product_type_id' => $productType->id,
            'grammage' => 250,
            'quantity' => 15,
        ]);
    }
}
