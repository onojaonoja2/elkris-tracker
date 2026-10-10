<?php

namespace Tests\Feature\Livewire;

use App\Enums\OrderStatus;
use App\Livewire\CsrOrderBreakdownTable;
use App\Livewire\DashboardBreakdownTable;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrderPaymentProofViewerTest extends TestCase
{
    use RefreshDatabase;

    public function test_accountant_can_view_and_close_payment_proof_from_delivered_breakdown(): void
    {
        $accountant = User::factory()->accountant()->create();
        $this->actingAs($accountant);

        $order = $this->createOrder($accountant, OrderStatus::Delivered, [
            'payment_proof_path' => 'receipts/payment-proofs/proof.png',
        ]);

        Livewire::test(DashboardBreakdownTable::class, ['type' => 'order', 'category' => 'delivered'])
            ->assertSee('Proof')
            ->assertSee('View Proof')
            ->call('viewProof', $order->id)
            ->assertSet('proofOrderId', $order->id)
            ->assertSee('Payment Proof — Order #'.$order->id)
            ->assertSee('Download')
            ->call('closeProof')
            ->assertSet('proofOrderId', null)
            ->assertDontSee('Payment Proof — Order #'.$order->id);
    }

    #[DataProvider('privilegedRoleProvider')]
    public function test_privileged_roles_can_open_payment_proof(string $role): void
    {
        $user = $this->createUserWithRole($role);
        $this->actingAs($user);

        $order = $role === 'supervisor'
            ? $this->createAssignedOrder($user, OrderStatus::Delivered)
            : $this->createOrder($user, OrderStatus::Delivered, [
                'payment_proof_path' => 'receipts/payment-proofs/proof.png',
            ]);

        Livewire::test(DashboardBreakdownTable::class, ['type' => 'order', 'category' => 'delivered'])
            ->assertSee('View Proof')
            ->call('viewProof', $order->id)
            ->assertSet('proofOrderId', $order->id)
            ->assertSee('Download');
    }

    public static function privilegedRoleProvider(): array
    {
        return [
            'accountant' => ['accountant'],
            'general_accountant' => ['general_accountant'],
            'supervisor' => ['supervisor'],
            'admin' => ['admin'],
            'manager' => ['manager'],
            'general_manager' => ['general_manager'],
        ];
    }

    public function test_unprivileged_role_cannot_open_payment_proof(): void
    {
        $sales = User::factory()->sales()->create();
        $this->actingAs($sales);

        $order = $this->createOrder($sales, OrderStatus::Delivered, [
            'payment_proof_path' => 'receipts/payment-proofs/proof.png',
        ]);

        Livewire::test(DashboardBreakdownTable::class, ['type' => 'order', 'category' => 'delivered'])
            ->assertSee('#'.$order->id)
            ->assertDontSee('View Proof')
            ->call('viewProof', $order->id)
            ->assertSet('proofOrderId', null)
            ->assertDontSee('Payment Proof — Order');
    }

    public function test_view_proof_ignores_orders_without_proof_or_not_delivered(): void
    {
        $accountant = User::factory()->accountant()->create();
        $this->actingAs($accountant);

        $withoutProof = $this->createOrder($accountant, OrderStatus::Delivered);
        $pendingWithProof = $this->createOrder($accountant, OrderStatus::Pending, [
            'payment_proof_path' => 'receipts/payment-proofs/proof.png',
        ]);

        Livewire::test(DashboardBreakdownTable::class, ['type' => 'order', 'category' => 'total'])
            ->assertDontSee('Payment Proof — Order')
            ->call('viewProof', $withoutProof->id)
            ->assertSet('proofOrderId', null)
            ->call('viewProof', $pendingWithProof->id)
            ->assertSet('proofOrderId', null)
            ->assertDontSee('Payment Proof — Order');
    }

    public function test_supervisor_can_view_payment_proof_from_csr_completed_orders(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $this->actingAs($supervisor);

        $csr = User::factory()->communitySalesRepresentative()->create();
        $order = $this->createOrder($supervisor, OrderStatus::Delivered, [
            'assigned_to' => $csr->id,
            'payment_proof_path' => 'receipts/payment-proofs/proof.png',
        ]);

        Livewire::test(CsrOrderBreakdownTable::class)
            ->call('selectCsr', $csr->id)
            ->assertSee('#'.$order->id)
            ->assertSee('View Proof')
            ->call('viewProof', $order->id)
            ->assertSet('proofOrderId', $order->id)
            ->assertSee('Payment Proof — Order #'.$order->id)
            ->assertSee('Download')
            ->call('closeProof')
            ->assertSet('proofOrderId', null);
    }

    public function test_csr_modal_hides_proof_button_when_missing(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $this->actingAs($supervisor);

        $csr = User::factory()->communitySalesRepresentative()->create();
        $order = $this->createOrder($supervisor, OrderStatus::Delivered, [
            'assigned_to' => $csr->id,
        ]);

        Livewire::test(CsrOrderBreakdownTable::class)
            ->call('selectCsr', $csr->id)
            ->assertSee('#'.$order->id)
            ->assertDontSee('View Proof');
    }

    private function createUserWithRole(string $role): User
    {
        return match ($role) {
            'general_accountant' => User::factory()->create(['role' => 'general_accountant']),
            'general_manager' => User::factory()->generalManager()->create(),
            'accountant' => User::factory()->accountant()->create(),
            'supervisor' => User::factory()->supervisor()->create(),
            'admin' => User::factory()->admin()->create(),
            'manager' => User::factory()->manager()->create(),
            default => User::factory()->create(['role' => $role]),
        };
    }

    private function createOrder(User $user, OrderStatus $status, array $attributes = []): Order
    {
        return Order::factory()->create(array_merge([
            'customer_id' => Customer::factory()->create()->id,
            'user_id' => $user->id,
            'status' => $status,
            'total_price' => 1000.00,
            'is_migrated_order' => false,
        ], $attributes));
    }

    private function createAssignedOrder(User $supervisor, OrderStatus $status): Order
    {
        $csr = User::factory()->communitySalesRepresentative()->create();
        $sales = User::factory()->sales()->create();

        return Order::factory()->create([
            'customer_id' => Customer::factory()->create()->id,
            'user_id' => $sales->id,
            'assigned_to' => $csr->id,
            'status' => $status,
            'total_price' => 1000.00,
            'is_migrated_order' => false,
            'payment_proof_path' => 'receipts/payment-proofs/proof.png',
        ]);
    }
}
