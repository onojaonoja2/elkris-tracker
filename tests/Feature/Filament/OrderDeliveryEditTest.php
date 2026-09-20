<?php

namespace Tests\Feature\Filament;

use App\Enums\AssignmentStatus;
use App\Enums\OrderStatus;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\RelationManagers\OrdersRelationManager;
use App\Filament\Resources\Orders\Pages\ManageOrders;
use App\Filament\Widgets\SalesPendingOrdersWidget;
use App\Models\AgentStock;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrderDeliveryEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_mark_assigned_order_delivered_from_orders_table(): void
    {
        $admin = User::factory()->admin()->create();
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

        $this->actingAs($admin);

        Livewire::test(ManageOrders::class)
            ->mountTableAction('edit', $order->id)
            ->set('mountedActions.0.data.status', 'delivered')
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $order->refresh();
        $this->assertEquals(OrderStatus::Delivered, $order->status);
        $this->assertEquals(AssignmentStatus::Delivered, $order->assignment_status);
        $this->assertSame(8, AgentStock::where('user_id', $csr->id)->first()->quantity);
    }

    public function test_delivered_requires_payment_proof_from_orders_table(): void
    {
        $admin = User::factory()->admin()->create();
        $sales = User::factory()->sales()->create();
        $csr = User::factory()->communitySalesRepresentative()->create();

        $order = $this->createOrderWithStock($csr, [
            'user_id' => $sales->id,
            'assigned_to' => $csr->id,
            'assignment_status' => AssignmentStatus::Accepted,
            'status' => OrderStatus::Assigned,
        ]);

        $this->actingAs($admin);

        Livewire::test(ManageOrders::class)
            ->mountTableAction('edit', $order->id)
            ->set('mountedActions.0.data.status', 'delivered')
            ->callMountedTableAction()
            ->assertHasTableActionErrors(['status']);

        $this->assertEquals(OrderStatus::Assigned, $order->fresh()->status);
    }

    public function test_unassigned_order_requires_sales_handler_from_orders_table(): void
    {
        $admin = User::factory()->admin()->create();
        $sales = User::factory()->sales()->create();
        $handler = User::factory()->sales()->create(['name' => 'Delivery Handler']);

        $order = $this->createOrderWithStock($handler, ['user_id' => $sales->id, 'total_price' => 2000]);
        $this->attachProof($order, $sales);

        $this->actingAs($admin);

        Livewire::test(ManageOrders::class)
            ->mountTableAction('edit', $order->id)
            ->set('mountedActions.0.data.status', 'delivered')
            ->callMountedTableAction()
            ->assertHasTableActionErrors();

        $this->assertEquals(OrderStatus::Pending, $order->fresh()->status);

        Livewire::test(ManageOrders::class)
            ->mountTableAction('edit', $order->id)
            ->set('mountedActions.0.data.status', 'delivered')
            ->set('mountedActions.0.data.delivered_by_sales', $handler->id)
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $order->refresh();
        $this->assertEquals(OrderStatus::Delivered, $order->status);
        $this->assertEquals(AssignmentStatus::Delivered, $order->assignment_status);
        $this->assertSame($handler->id, $order->assigned_to);
    }

    public function test_epa_marks_assigned_order_delivered_through_service(): void
    {
        $rep = User::factory()->rep()->create();
        $sales = User::factory()->sales()->create();
        $csr = User::factory()->communitySalesRepresentative()->create();
        $customer = Customer::factory()->create();

        $order = $this->createOrderWithStock($csr, [
            'customer_id' => $customer->id,
            'user_id' => $rep->id,
            'assigned_to' => $csr->id,
            'assigned_by' => $sales->id,
            'assignment_status' => AssignmentStatus::Accepted,
            'status' => OrderStatus::Assigned,
            'total_price' => 2000,
        ]);
        $this->attachProof($order, $rep);

        $this->actingAs($rep);

        Livewire::test(OrdersRelationManager::class, [
            'ownerRecord' => $customer,
            'pageClass' => EditCustomer::class,
        ])
            ->callTableAction('edit', $order->getKey(), data: ['status' => 'delivered'])
            ->assertHasNoTableActionErrors();

        $order->refresh();
        $this->assertEquals(OrderStatus::Delivered, $order->status);
        $this->assertEquals(AssignmentStatus::Delivered, $order->assignment_status);
        $this->assertSame(8, AgentStock::where('user_id', $csr->id)->first()->quantity);
    }

    public function test_epa_cannot_mark_assigned_order_delivered_without_proof(): void
    {
        $rep = User::factory()->rep()->create();
        $sales = User::factory()->sales()->create();
        $csr = User::factory()->communitySalesRepresentative()->create();
        $customer = Customer::factory()->create();

        $order = $this->createOrderWithStock($csr, [
            'customer_id' => $customer->id,
            'user_id' => $rep->id,
            'assigned_to' => $csr->id,
            'assignment_status' => AssignmentStatus::Accepted,
            'status' => OrderStatus::Assigned,
        ]);

        $this->actingAs($rep);

        Livewire::test(OrdersRelationManager::class, [
            'ownerRecord' => $customer,
            'pageClass' => EditCustomer::class,
        ])
            ->callTableAction('edit', $order->getKey(), data: ['status' => 'delivered'])
            ->assertHasTableActionErrors(['status']);

        $this->assertEquals(OrderStatus::Assigned, $order->fresh()->status);
        $this->assertEquals(AssignmentStatus::Accepted, $order->fresh()->assignment_status);
    }

    public function test_delivered_orders_hide_edit_and_delete_in_relation_manager(): void
    {
        $rep = User::factory()->rep()->create();
        $customer = Customer::factory()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'user_id' => $rep->id,
            'status' => OrderStatus::Delivered,
        ]);

        $this->actingAs($rep);

        Livewire::test(OrdersRelationManager::class, [
            'ownerRecord' => $customer,
            'pageClass' => EditCustomer::class,
        ])
            ->assertTableActionHidden('edit', $order->getKey())
            ->assertTableActionHidden('delete', $order->getKey());
    }

    public function test_sales_process_order_still_delivers_through_service(): void
    {
        $sales = User::factory()->sales()->create();

        $order = $this->createOrderWithStock($sales, ['user_id' => $sales->id, 'total_price' => 2000]);
        $this->attachProof($order, $sales);

        $this->actingAs($sales);

        Livewire::test(SalesPendingOrdersWidget::class)
            ->callTableAction('processOrder', $order->id)
            ->assertHasNoTableActionErrors();

        $order->refresh();
        $this->assertEquals(OrderStatus::Delivered, $order->status);
        $this->assertEquals(AssignmentStatus::Delivered, $order->assignment_status);
        $this->assertSame($sales->id, $order->assigned_to);
        $this->assertSame(8, AgentStock::where('user_id', $sales->id)->first()->quantity);
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

    private function attachProof(Order $order, User $uploader): Order
    {
        $order->update([
            'payment_proof_path' => 'receipts/payment-proofs/proof.png',
            'payment_proof_uploaded_by' => $uploader->id,
            'payment_proof_uploaded_at' => now(),
        ]);

        return $order->fresh();
    }
}
