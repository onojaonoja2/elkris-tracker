<?php

namespace Tests\Feature\Services;

use App\Models\AgentStock;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\User;
use App\Services\OrderProductBackfillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderProductBackfillServiceTest extends TestCase
{
    use RefreshDatabase;

    private function createOrderProduct(array $attributes = []): Product
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'user_id' => User::factory()->create()->id,
        ]);

        return Product::create(array_merge([
            'order_id' => $order->id,
            'product_type_id' => null,
            'product_name' => 'Elkris Oat Flour',
            'grammage' => 1300,
            'quantity' => 2,
            'price' => 500.00,
        ], $attributes));
    }

    public function test_backfills_product_type_id_by_exact_name(): void
    {
        $product = $this->createOrderProduct();
        $productType = ProductType::factory()->create(['name' => 'Elkris Oat Flour']);

        $updated = OrderProductBackfillService::backfillOrderProducts();

        $this->assertSame(1, $updated);
        $this->assertSame($productType->id, $product->fresh()->product_type_id);
        $this->assertSame('Elkris Oat Flour', $product->fresh()->product_name);
    }

    public function test_backfills_product_type_id_via_legacy_name_alias(): void
    {
        $productType = ProductType::factory()->create(['name' => 'Elkris Plantain Flour']);
        $product = $this->createOrderProduct([
            'product_name' => 'Elkris Plantain',
            'grammage' => 1800,
            'quantity' => 6,
            'price' => 2500.00,
        ]);

        $updated = OrderProductBackfillService::backfillOrderProducts();

        $this->assertSame(1, $updated);
        $this->assertSame($productType->id, $product->fresh()->product_type_id);
        $this->assertSame('Elkris Plantain Flour', $product->fresh()->product_name);
    }

    public function test_leaves_unmatched_products_untouched(): void
    {
        $product = $this->createOrderProduct([
            'product_name' => 'Unknown Legacy Product',
        ]);

        $updated = OrderProductBackfillService::backfillOrderProducts();

        $this->assertSame(0, $updated);
        $this->assertNull($product->fresh()->product_type_id);
        $this->assertSame('Unknown Legacy Product', $product->fresh()->product_name);
    }

    public function test_fixes_misspelled_agent_stock_name(): void
    {
        $user = User::factory()->create();
        $productType = ProductType::factory()->create();
        AgentStock::create([
            'user_id' => $user->id,
            'product_type_id' => $productType->id,
            'product_name' => 'Elkris Oat Flourx',
            'grammage' => 1300,
            'quantity' => 10,
        ]);

        $updated = OrderProductBackfillService::fixMisspelledAgentStockNames();

        $this->assertSame(1, $updated);
        $this->assertSame('Elkris Oat Flour', AgentStock::first()->product_name);
    }

    public function test_merges_misspelled_stock_into_existing_row(): void
    {
        $user = User::factory()->create();
        $productType = ProductType::factory()->create();
        $existing = AgentStock::create([
            'user_id' => $user->id,
            'product_type_id' => $productType->id,
            'product_name' => 'Elkris Oat Flour',
            'grammage' => 1300,
            'quantity' => 35,
        ]);
        AgentStock::create([
            'user_id' => $user->id,
            'product_type_id' => $productType->id,
            'product_name' => 'Elkris Oat Flourx',
            'grammage' => 1300,
            'quantity' => 10,
        ]);

        $updated = OrderProductBackfillService::fixMisspelledAgentStockNames();

        $this->assertSame(1, $updated);
        $this->assertSame(45, $existing->fresh()->quantity);
        $this->assertSame(1, AgentStock::count());
    }
}
