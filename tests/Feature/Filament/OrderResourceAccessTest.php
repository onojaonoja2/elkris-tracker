<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderResourceAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_accountant_can_view_orders_index(): void
    {
        $accountant = User::factory()->accountant()->create();

        $this->actingAs($accountant)
            ->get(OrderResource::getUrl('index'))
            ->assertOk();
    }

    public function test_general_accountant_can_view_orders_index(): void
    {
        $generalAccountant = User::factory()->state(['role' => 'general_accountant'])->create();

        $this->actingAs($generalAccountant)
            ->get(OrderResource::getUrl('index'))
            ->assertOk();
    }

    public function test_orders_appear_in_account_navigation_group(): void
    {
        $accountant = User::factory()->accountant()->create();
        $this->actingAs($accountant);

        $this->assertTrue(OrderResource::shouldRegisterNavigation());
        $this->assertSame('Accountant', OrderResource::getNavigationGroup());
    }

    public function test_account_roles_cannot_create_orders(): void
    {
        $accountant = User::factory()->accountant()->create();
        $this->actingAs($accountant);
        $this->assertFalse(OrderResource::canCreate());

        $generalAccountant = User::factory()->state(['role' => 'general_accountant'])->create();
        $this->actingAs($generalAccountant);
        $this->assertFalse(OrderResource::canCreate());

        $sales = User::factory()->sales()->create();
        $this->actingAs($sales);
        $this->assertTrue(OrderResource::canCreate());
    }

    public function test_account_roles_see_all_orders_not_just_their_own(): void
    {
        $sales = User::factory()->sales()->create();
        Order::factory()->count(3)->create(['user_id' => $sales->id]);

        $accountant = User::factory()->accountant()->create();
        $this->actingAs($accountant);

        $this->assertSame(3, OrderResource::getEloquentQuery()->count());
    }

    public function test_unauthorized_roles_still_cannot_access_orders(): void
    {
        $csr = User::factory()->communitySalesRepresentative()->create();

        $this->actingAs($csr)
            ->get(OrderResource::getUrl('index'))
            ->assertForbidden();
    }
}
