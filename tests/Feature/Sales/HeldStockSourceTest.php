<?php

namespace Tests\Feature\Sales;

use App\Filament\Resources\SalesRecords\Pages\CreateSalesRecord;
use App\Models\AgentStock;
use App\Models\Inventory;
use App\Models\ProductType;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\SalesRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class HeldStockSourceTest extends TestCase
{
    use RefreshDatabase;

    private ProductType $productType;

    /**
     * @var array<string, mixed>
     */
    private array $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productType = ProductType::factory()->create([
            'name' => 'Ora Herbal Mix',
            'available_grammages' => [['grammage' => 100, 'carton_quantity' => 20]],
        ]);

        $this->product = [
            'product_name' => $this->productType->name,
            'grammage' => 100,
            'quantity' => 5,
            'price' => 1000.00,
        ];
    }

    private function giveHeldStock(User $agent, int $quantity): AgentStock
    {
        return AgentStock::factory()->create([
            'user_id' => $agent->id,
            'product_type_id' => $this->productType->id,
            'product_name' => $this->productType->name,
            'grammage' => 100,
            'quantity' => $quantity,
        ]);
    }

    private function makeWarehouseWithStock(int $quantity): Warehouse
    {
        $warehouse = Warehouse::factory()->create();

        Inventory::factory()->create([
            'warehouse_id' => $warehouse->id,
            'product_type_id' => $this->productType->id,
            'grammage' => 100,
            'quantity' => $quantity,
        ]);

        return $warehouse;
    }

    public function test_open_market_can_submit_sale_from_held_stock(): void
    {
        $agent = User::factory()->openMarket()->create();
        $this->giveHeldStock($agent, 20);

        $this->actingAs($agent);

        $record = SalesRecordService::submitSale([
            'stock_source' => 'held',
            'products' => [$this->product],
            'total_value' => 5000.00,
            'is_credit' => false,
        ]);

        $this->assertDatabaseHas('sales_records', [
            'id' => $record->id,
            'agent_id' => $agent->id,
            'agent_type' => 'open_market',
            'stock_source' => 'held',
            'status' => 'pending',
        ]);

        $this->assertNotNull($record->fresh()->stock_deducted_at);

        $this->assertDatabaseHas('agent_stocks', [
            'user_id' => $agent->id,
            'product_name' => $this->productType->name,
            'grammage' => 100,
            'quantity' => 15,
        ]);

        $this->assertDatabaseMissing('stock_transfers', ['sales_record_id' => $record->id]);
    }

    public function test_open_market_held_stock_blocks_when_insufficient(): void
    {
        $agent = User::factory()->openMarket()->create();
        $this->giveHeldStock($agent, 2);

        $this->actingAs($agent);

        try {
            SalesRecordService::submitSale([
                'stock_source' => 'held',
                'products' => [$this->product],
                'total_value' => 5000.00,
                'is_credit' => false,
            ]);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('products', $e->errors());
        }

        $this->assertDatabaseMissing('sales_records', ['agent_id' => $agent->id]);
        $this->assertDatabaseHas('agent_stocks', [
            'user_id' => $agent->id,
            'product_name' => $this->productType->name,
            'grammage' => 100,
            'quantity' => 2,
        ]);
    }

    public function test_retail_market_credit_sale_from_held_stock(): void
    {
        $agent = User::factory()->retailMarket()->create();
        $this->giveHeldStock($agent, 20);

        $this->actingAs($agent);

        $record = SalesRecordService::submitSale([
            'stock_source' => 'held',
            'products' => [$this->product],
            'total_value' => 5000.00,
            'is_credit' => true,
        ]);

        $this->assertDatabaseHas('sales_records', [
            'id' => $record->id,
            'agent_id' => $agent->id,
            'agent_type' => 'retail_market',
            'stock_source' => 'held',
            'is_credit' => true,
            'credit_status' => 'pending_payment',
        ]);

        $this->assertNotNull($record->fresh()->stock_deducted_at);

        $this->assertDatabaseMissing('stock_transfers', ['sales_record_id' => $record->id]);
    }

    public function test_warehouse_source_creates_stock_request(): void
    {
        $agent = User::factory()->openMarket()->create();
        $warehouse = $this->makeWarehouseWithStock(20);

        $this->actingAs($agent);

        $record = SalesRecordService::submitSale([
            'stock_source' => 'warehouse',
            'products' => [$this->product],
            'total_value' => 5000.00,
            'is_credit' => false,
            'warehouse_id' => $warehouse->id,
        ]);

        $this->assertDatabaseHas('sales_records', [
            'id' => $record->id,
            'stock_source' => 'warehouse',
            'warehouse_id' => $warehouse->id,
            'stock_deducted_at' => null,
        ]);

        $this->assertDatabaseHas('stock_transfers', [
            'sales_record_id' => $record->id,
            'status' => 'requested',
        ]);
    }

    public function test_approve_held_source_does_not_allocate_warehouse_stock(): void
    {
        $agent = User::factory()->openMarket()->create();
        $this->giveHeldStock($agent, 20);
        $accountant = User::factory()->accountant()->create();

        $this->actingAs($agent);

        $record = SalesRecordService::submitSale([
            'stock_source' => 'held',
            'products' => [$this->product],
            'total_value' => 5000.00,
            'is_credit' => false,
        ]);

        SalesRecordService::approve($record, [], $accountant->id);

        $this->assertDatabaseHas('sales_records', [
            'id' => $record->id,
            'status' => 'approved',
        ]);

        $this->assertDatabaseHas('agent_stocks', [
            'user_id' => $agent->id,
            'product_name' => $this->productType->name,
            'grammage' => 100,
            'quantity' => 15,
        ]);

        $this->assertDatabaseMissing('stock_transfers', ['sales_record_id' => $record->id]);
        $this->assertDatabaseMissing('inventories', ['product_type_id' => $this->productType->id]);
    }

    public function test_reject_held_source_restores_held_stock(): void
    {
        $agent = User::factory()->openMarket()->create();
        $this->giveHeldStock($agent, 20);
        $accountant = User::factory()->accountant()->create();

        $this->actingAs($agent);

        $record = SalesRecordService::submitSale([
            'stock_source' => 'held',
            'products' => [$this->product],
            'total_value' => 5000.00,
            'is_credit' => false,
        ]);

        SalesRecordService::reject($record, 'Invalid receipt', $accountant->id);

        $this->assertDatabaseHas('sales_records', [
            'id' => $record->id,
            'status' => 'rejected',
        ]);

        $this->assertDatabaseHas('agent_stocks', [
            'user_id' => $agent->id,
            'product_name' => $this->productType->name,
            'grammage' => 100,
            'quantity' => 20,
        ]);
    }

    public function test_stock_source_field_visible_for_open_market_and_hidden_for_csr(): void
    {
        $this->actingAs(User::factory()->openMarket()->create());

        Livewire::test(CreateSalesRecord::class)
            ->assertFormFieldVisible('stock_source')
            ->assertFormFieldVisible('warehouse_id');

        $this->actingAs(User::factory()->communitySalesRepresentative()->create());

        Livewire::test(CreateSalesRecord::class)
            ->assertFormFieldHidden('stock_source')
            ->assertFormFieldHidden('warehouse_id');
    }

    public function test_warehouse_id_hidden_when_held_selected(): void
    {
        $this->actingAs(User::factory()->openMarket()->create());

        Livewire::test(CreateSalesRecord::class)
            ->set('data.stock_source', 'held')
            ->assertFormFieldHidden('warehouse_id');
    }

    public function test_open_market_can_submit_held_credit_sale_via_form(): void
    {
        $agent = User::factory()->openMarket()->create();
        $this->giveHeldStock($agent, 50);

        $this->actingAs($agent);

        Livewire::test(CreateSalesRecord::class)
            ->fillForm([
                'vendor_name' => 'Doe Market',
                'is_credit' => true,
                'stock_source' => 'held',
                'customer_name' => 'Jane Doe',
                'customer_phone' => '08012345678',
                'expected_collection_date' => now()->addDays(7)->toDateString(),
                'products' => [[
                    'product_name' => $this->productType->name,
                    'grammage' => '100',
                    'cartons' => 0,
                    'pieces' => 2,
                    'quantity' => 2,
                    'price' => 1000,
                ]],
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('sales_records', [
            'agent_id' => $agent->id,
            'stock_source' => 'held',
            'is_credit' => true,
            'credit_status' => 'pending_payment',
            'warehouse_id' => null,
        ]);

        $this->assertDatabaseHas('agent_stocks', [
            'user_id' => $agent->id,
            'product_name' => $this->productType->name,
            'grammage' => 100,
            'quantity' => 48,
        ]);

        $this->assertDatabaseMissing('stock_transfers', ['to_agent_id' => $agent->id]);
    }
}
