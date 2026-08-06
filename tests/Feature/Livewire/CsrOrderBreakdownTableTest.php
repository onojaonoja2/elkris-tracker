<?php

namespace Tests\Feature\Livewire;

use App\Enums\OrderStatus;
use App\Livewire\CsrOrderBreakdownTable;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use Tests\TestCase;

class CsrOrderBreakdownTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_shows_completed_order_count_per_csr(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $this->actingAs($supervisor);

        $csrA = User::factory()->communitySalesRepresentative()->create();
        $csrB = User::factory()->communitySalesRepresentative()->create();

        $this->createOrder($csrA, OrderStatus::Delivered);
        $this->createOrder($csrA, OrderStatus::Delivered);
        $this->createOrder($csrA, OrderStatus::Pending);
        $this->createOrder($csrB, OrderStatus::Delivered);

        Livewire::test(CsrOrderBreakdownTable::class)
            ->assertSee($csrA->name)
            ->assertSee($csrB->name)
            ->assertSeeText('2')
            ->assertSeeText('1');
    }

    public function test_summary_includes_csrs_without_completed_orders(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $this->actingAs($supervisor);

        $csrA = User::factory()->communitySalesRepresentative()->create();
        $csrB = User::factory()->communitySalesRepresentative()->create();

        $this->createOrder($csrA, OrderStatus::Delivered);

        Livewire::test(CsrOrderBreakdownTable::class)
            ->assertSee($csrA->name)
            ->assertSee($csrB->name)
            ->assertSeeText('1')
            ->assertSeeText('0');
    }

    public function test_summary_excludes_pending_and_non_csr_orders(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $this->actingAs($supervisor);

        $csr = User::factory()->communitySalesRepresentative()->create();
        $sales = User::factory()->sales()->create();

        $this->createOrder($csr, OrderStatus::Delivered);
        $this->createOrder($csr, OrderStatus::Pending);
        $this->createOrder($sales, OrderStatus::Delivered);

        Livewire::test(CsrOrderBreakdownTable::class)
            ->assertSee($csr->name)
            ->assertDontSee($sales->name)
            ->assertSeeText('1')
            ->assertDontSeeText('2');
    }

    public function test_drill_down_shows_selected_csr_delivered_orders(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $this->actingAs($supervisor);

        $csr = User::factory()->communitySalesRepresentative()->create();
        $sales = User::factory()->sales()->create();

        $deliveredA = $this->createOrder($csr, OrderStatus::Delivered);
        $deliveredB = $this->createOrder($csr, OrderStatus::Delivered);
        $pending = $this->createOrder($csr, OrderStatus::Pending);
        $salesOrder = $this->createOrder($sales, OrderStatus::Delivered);

        Livewire::test(CsrOrderBreakdownTable::class)
            ->call('selectCsr', $csr->id)
            ->assertSee('#'.$deliveredA->id)
            ->assertSee('#'.$deliveredB->id)
            ->assertDontSee('#'.$pending->id)
            ->assertDontSee('#'.$salesOrder->id);
    }

    public function test_search_filters_summary_by_csr_name(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $this->actingAs($supervisor);

        $csrA = User::factory()->communitySalesRepresentative()->create();
        $csrB = User::factory()->communitySalesRepresentative()->create();

        $this->createOrder($csrA, OrderStatus::Delivered);
        $this->createOrder($csrB, OrderStatus::Delivered);

        Livewire::test(CsrOrderBreakdownTable::class)
            ->set('search', $csrA->name)
            ->assertSee($csrA->name)
            ->assertDontSee($csrB->name);
    }

    public function test_respects_dashboard_date_range(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $this->actingAs($supervisor);

        $csr = User::factory()->communitySalesRepresentative()->create();

        $this->createOrder($csr, OrderStatus::Delivered, ['created_at' => now()->subDays(5)]);
        $this->createOrder($csr, OrderStatus::Delivered);

        Session::put('dashboard_date_from', now()->subDay()->toDateTimeString());
        Session::put('dashboard_date_to', now()->toDateTimeString());

        Livewire::test(CsrOrderBreakdownTable::class)
            ->assertSeeText('1');
    }

    public function test_unauthorized_role_is_rejected(): void
    {
        $user = User::factory()->sales()->create();
        $this->actingAs($user);

        Livewire::test(CsrOrderBreakdownTable::class)
            ->assertForbidden();
    }

    public function test_drill_down_paginates_by_ten(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $this->actingAs($supervisor);

        $csr = User::factory()->communitySalesRepresentative()->create();

        $orders = collect(range(1, 12))->map(
            fn (int $i) => $this->createOrder($csr, OrderStatus::Delivered, ['created_at' => now()->addMinutes($i)])
        );

        Livewire::test(CsrOrderBreakdownTable::class)
            ->call('selectCsr', $csr->id)
            ->assertSee('>#'.$orders[11]->id.'<', false)
            ->assertSee('>#'.$orders[2]->id.'<', false)
            ->assertDontSee('>#'.$orders[0]->id.'<', false)
            ->assertDontSee('>#'.$orders[1]->id.'<', false)
            ->call('setPage', 2)
            ->assertSee('>#'.$orders[0]->id.'<', false)
            ->assertSee('>#'.$orders[1]->id.'<', false)
            ->assertDontSee('>#'.$orders[11]->id.'<', false);
    }

    public function test_summary_paginates_by_ten(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $this->actingAs($supervisor);

        $csrs = collect(range(1, 12))->map(
            fn (int $i) => User::factory()->communitySalesRepresentative()->create(['name' => 'CSR '.chr(64 + $i)])
        );

        foreach (range(1, 12) as $i) {
            foreach (range(1, $i) as $ignored) {
                $this->createOrder($csrs[$i - 1], OrderStatus::Delivered);
            }
        }

        Livewire::test(CsrOrderBreakdownTable::class)
            ->assertSee('CSR L')
            ->assertSee('CSR C')
            ->assertDontSee('CSR A')
            ->assertDontSee('CSR B')
            ->call('setPage', 2)
            ->assertSee('CSR A')
            ->assertSee('CSR B')
            ->assertDontSee('CSR L');
    }

    public function test_summary_ignores_csr_submitted_orders_that_are_not_assigned(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $csr = User::factory()->communitySalesRepresentative()->create();
        $this->actingAs($supervisor);

        Order::factory()->create([
            'customer_id' => Customer::factory()->create()->id,
            'user_id' => $csr->id,
            'status' => OrderStatus::Delivered,
            'total_price' => 1000.00,
            'is_migrated_order' => false,
        ]);

        Livewire::test(CsrOrderBreakdownTable::class)
            ->assertSee($csr->name)
            ->assertSeeText('0');
    }

    private function createOrder(User $user, OrderStatus $status, array $attributes = []): Order
    {
        return Order::factory()->create(array_merge([
            'customer_id' => Customer::factory()->create()->id,
            'user_id' => User::factory()->rep()->create()->id,
            'assigned_to' => $user->id,
            'status' => $status,
            'total_price' => 1000.00,
            'is_migrated_order' => false,
        ], $attributes));
    }
}
