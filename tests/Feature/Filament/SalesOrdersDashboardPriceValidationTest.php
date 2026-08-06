<?php

namespace Tests\Feature\Filament;

use App\Enums\OrderStatus;
use App\Filament\Pages\SalesOrdersDashboard;
use App\Models\Customer;
use App\Models\ProductType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SalesOrdersDashboardPriceValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_initiate_order_rejects_zero_unit_price(): void
    {
        $sales = User::factory()->sales()->create();
        $customer = Customer::factory()->create();
        $productType = ProductType::factory()->create(['available_grammages' => [500]]);

        $this->actingAs($sales);

        Livewire::test(SalesOrdersDashboard::class)
            ->mountAction('initiateOrder')
            ->set('mountedActions.0.data.customer_id', $customer->id)
            ->set('mountedActions.0.data.items', [[
                'product_type_id' => $productType->id,
                'grammage' => '500',
                'quantity' => 2,
                'price' => 0,
            ]])
            ->callMountedAction()
            ->assertHasActionErrors(['items.0.price' => 'min']);

        $this->assertDatabaseMissing('orders', [
            'customer_id' => $customer->id,
        ]);
    }

    public function test_initiate_order_creates_order_with_positive_price(): void
    {
        $sales = User::factory()->sales()->create();
        $customer = Customer::factory()->create();
        $productType = ProductType::factory()->create(['name' => 'Ora herbal mix', 'available_grammages' => [500]]);

        $this->actingAs($sales);

        Livewire::test(SalesOrdersDashboard::class)
            ->mountAction('initiateOrder')
            ->set('mountedActions.0.data.customer_id', $customer->id)
            ->set('mountedActions.0.data.items', [[
                'product_type_id' => $productType->id,
                'grammage' => '500',
                'quantity' => 2,
                'price' => 1500,
            ]])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('orders', [
            'customer_id' => $customer->id,
            'user_id' => $sales->id,
            'status' => OrderStatus::Pending->value,
            'total_price' => 3000,
        ]);

        $this->assertDatabaseHas('products', [
            'product_type_id' => $productType->id,
            'grammage' => '500',
            'quantity' => 2,
            'price' => 1500,
        ]);
    }
}
