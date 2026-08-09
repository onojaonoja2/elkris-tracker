<?php

namespace Tests\Feature\Sales;

use App\Filament\Resources\SalesRecords\Pages\CreateSalesRecord;
use App\Models\Inventory;
use App\Models\ProductType;
use App\Models\SalesRecord;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CreateSalesRecordPageTest extends TestCase
{
    use RefreshDatabase;

    private User $retailUser;

    private ProductType $productType;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->retailUser = User::factory()->state(['role' => 'retail_market'])->create();
        $this->productType = ProductType::factory()->create([
            'name' => 'Ora herbal mix',
            'available_grammages' => [
                ['grammage' => 100, 'carton_quantity' => 20],
                200,
                500,
            ],
        ]);
        $this->warehouse = Warehouse::factory()->create([
            'sales_person_id' => $this->retailUser->id,
        ]);

        $this->actingAs($this->retailUser);
    }

    private function formData(int $quantity): array
    {
        return [
            'business_name' => 'Peter Retail Store',
            'is_credit' => true,
            'customer_name' => 'Jane Doe',
            'customer_phone' => '08012345678',
            'expected_collection_date' => now()->addDays(7)->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'products' => [[
                'product_name' => 'Ora herbal mix',
                'grammage' => '100',
                'cartons' => intdiv($quantity, 20),
                'pieces' => $quantity % 20,
                'quantity' => $quantity,
                'price' => 1000,
            ]],
            'total_value' => 1000 * $quantity,
        ];
    }

    private function makeWarehouseStock(int $quantity): void
    {
        Inventory::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'product_type_id' => $this->productType->id,
            'grammage' => 100,
            'quantity' => $quantity,
        ]);
    }

    private function baseFormData(): array
    {
        return [
            'business_name' => 'Peter Retail Store',
            'is_credit' => true,
            'customer_name' => 'Jane Doe',
            'customer_phone' => '08012345678',
            'expected_collection_date' => now()->addDays(7)->toDateString(),
            'warehouse_id' => $this->warehouse->id,
        ];
    }

    public function test_retail_user_can_create_sales_record_and_gets_success_toast(): void
    {
        $this->makeWarehouseStock(43);

        Livewire::test(CreateSalesRecord::class)
            ->fillForm($this->formData(43))
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified()
            ->assertRedirect();

        $this->assertDatabaseHas('sales_records', [
            'agent_id' => $this->retailUser->id,
            'agent_type' => 'retail_market',
            'warehouse_id' => $this->warehouse->id,
            'status' => 'pending',
            'is_credit' => true,
            'total_value' => 43000.00,
        ]);

        $this->assertDatabaseMissing('agent_stocks', ['user_id' => $this->retailUser->id]);

        $this->assertDatabaseHas('inventories', [
            'warehouse_id' => $this->warehouse->id,
            'product_type_id' => $this->productType->id,
            'grammage' => 100,
            'quantity' => 43,
        ]);

        $transfer = StockTransfer::where('from_warehouse_id', $this->warehouse->id)
            ->where('to_agent_id', $this->retailUser->id)
            ->where('status', 'requested')
            ->firstOrFail();

        $this->assertDatabaseHas('stock_transfer_items', [
            'stock_transfer_id' => $transfer->id,
            'product_type_id' => $this->productType->id,
            'grammage' => 100,
            'quantity' => 43,
        ]);
    }

    public function test_cartons_and_units_are_combined_into_total_pieces(): void
    {
        $this->makeWarehouseStock(43);

        Livewire::test(CreateSalesRecord::class)
            ->fillForm(array_merge($this->baseFormData(), [
                'products' => [[
                    'product_name' => 'Ora herbal mix',
                    'grammage' => '100',
                    'cartons' => 0,
                    'pieces' => 1,
                    'price' => 1000,
                ]],
            ]))
            ->set('data.products.0.cartons', 2)
            ->set('data.products.0.pieces', 3)
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $record = SalesRecord::where('agent_id', $this->retailUser->id)->firstOrFail();

        $this->assertSame(43, $record->products[0]['quantity']);
        $this->assertSame(43000.0, (float) $record->total_value);

        $this->assertDatabaseHas('stock_transfer_items', [
            'product_type_id' => $this->productType->id,
            'grammage' => 100,
            'quantity' => 43,
        ]);
    }

    public function test_retail_user_can_create_sales_record_with_only_units(): void
    {
        $this->makeWarehouseStock(5);

        Livewire::test(CreateSalesRecord::class)
            ->fillForm(array_merge($this->baseFormData(), [
                'products' => [[
                    'product_name' => 'Ora herbal mix',
                    'grammage' => '100',
                    'cartons' => 0,
                    'pieces' => 5,
                    'price' => 1000,
                ]],
            ]))
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $record = SalesRecord::where('agent_id', $this->retailUser->id)->firstOrFail();

        $this->assertSame(5, $record->products[0]['quantity']);

        $this->assertDatabaseHas('stock_transfer_items', [
            'product_type_id' => $this->productType->id,
            'grammage' => 100,
            'quantity' => 5,
        ]);
    }

    public function test_retail_user_can_create_sales_record_with_only_cartons(): void
    {
        $this->makeWarehouseStock(40);

        Livewire::test(CreateSalesRecord::class)
            ->fillForm(array_merge($this->baseFormData(), [
                'products' => [[
                    'product_name' => 'Ora herbal mix',
                    'grammage' => '100',
                    'cartons' => 2,
                    'pieces' => 0,
                    'price' => 1000,
                ]],
            ]))
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $record = SalesRecord::where('agent_id', $this->retailUser->id)->firstOrFail();

        $this->assertSame(40, $record->products[0]['quantity']);

        $this->assertDatabaseHas('stock_transfer_items', [
            'product_type_id' => $this->productType->id,
            'grammage' => 100,
            'quantity' => 40,
        ]);
    }

    public function test_cartons_and_units_cannot_both_be_zero(): void
    {
        $this->makeWarehouseStock(43);

        Livewire::test(CreateSalesRecord::class)
            ->fillForm(array_merge($this->baseFormData(), [
                'products' => [[
                    'product_name' => 'Ora herbal mix',
                    'grammage' => '100',
                    'cartons' => 0,
                    'pieces' => 0,
                    'price' => 1000,
                ]],
            ]))
            ->call('create')
            ->assertHasFormErrors(['products.0.pieces']);

        $this->assertDatabaseMissing('sales_records', [
            'agent_id' => $this->retailUser->id,
        ]);
    }

    public function test_retail_user_gets_danger_toast_when_stock_is_insufficient(): void
    {
        $this->makeWarehouseStock(40);

        Livewire::test(CreateSalesRecord::class)
            ->fillForm($this->formData(43))
            ->call('create')
            ->assertHasErrors(['products'])
            ->assertNotified();

        $this->assertDatabaseMissing('sales_records', [
            'agent_id' => $this->retailUser->id,
        ]);

        $this->assertDatabaseMissing('stock_transfers', [
            'from_warehouse_id' => $this->warehouse->id,
        ]);

        $this->assertDatabaseHas('inventories', [
            'warehouse_id' => $this->warehouse->id,
            'product_type_id' => $this->productType->id,
            'grammage' => 100,
            'quantity' => 40,
        ]);
    }

    public function test_retail_user_gets_form_error_when_business_name_is_missing(): void
    {
        $data = $this->formData(1);
        $data['business_name'] = null;

        Livewire::test(CreateSalesRecord::class)
            ->fillForm($data)
            ->call('create')
            ->assertHasFormErrors(['business_name']);

        $this->assertDatabaseMissing('sales_records', [
            'agent_id' => $this->retailUser->id,
        ]);
    }
}
