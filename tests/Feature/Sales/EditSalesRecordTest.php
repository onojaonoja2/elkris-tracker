<?php

namespace Tests\Feature\Sales;

use App\Filament\Resources\SalesRecords\Pages\EditSalesRecord;
use App\Models\ProductType;
use App\Models\SalesRecord;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EditSalesRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_form_prefills_cartons_and_units_from_stored_pieces(): void
    {
        $admin = User::factory()->admin()->create();
        $agent = User::factory()->retailMarket()->create();
        $productType = ProductType::factory()->create([
            'available_grammages' => [
                ['grammage' => 100, 'carton_quantity' => 20],
                200,
                500,
            ],
        ]);
        $warehouse = Warehouse::factory()->create();

        $record = SalesRecord::create([
            'agent_id' => $agent->id,
            'agent_type' => 'retail_market',
            'warehouse_id' => $warehouse->id,
            'business_name' => 'Test Store',
            'customer_name' => 'Jane Doe',
            'customer_phone' => '08012345678',
            'expected_collection_date' => now()->addDays(7)->toDateString(),
            'status' => 'pending',
            'is_credit' => true,
            'credit_status' => 'pending_payment',
            'total_value' => 43000,
            'products' => [[
                'product_name' => $productType->name,
                'grammage' => 100,
                'quantity' => 43,
                'price' => 1000,
            ]],
        ]);

        $this->actingAs($admin);

        $component = Livewire::test(EditSalesRecord::class, ['record' => $record->getKey()]);

        $component
            ->assertSet('data.products', function (array $products): bool {
                $first = array_values($products)[0];

                return (int) ($first['cartons'] ?? 0) === 2 && (int) ($first['pieces'] ?? 0) === 3;
            });
    }

    public function test_saving_edit_persists_quantity_in_pieces(): void
    {
        $admin = User::factory()->admin()->create();
        $agent = User::factory()->retailMarket()->create();
        $productType = ProductType::factory()->create([
            'available_grammages' => [
                ['grammage' => 100, 'carton_quantity' => 20],
                200,
                500,
            ],
        ]);
        $warehouse = Warehouse::factory()->create();

        $record = SalesRecord::create([
            'agent_id' => $agent->id,
            'agent_type' => 'retail_market',
            'warehouse_id' => $warehouse->id,
            'business_name' => 'Test Store',
            'customer_name' => 'Jane Doe',
            'customer_phone' => '08012345678',
            'expected_collection_date' => now()->addDays(7)->toDateString(),
            'status' => 'pending',
            'is_credit' => true,
            'credit_status' => 'pending_payment',
            'total_value' => 43000,
            'products' => [[
                'product_name' => $productType->name,
                'grammage' => 100,
                'quantity' => 43,
                'price' => 1000,
            ]],
        ]);

        $this->actingAs($admin);

        Livewire::test(EditSalesRecord::class, ['record' => $record->getKey()])
            ->fillForm([
                'is_credit' => true,
                'business_name' => 'Test Store',
                'customer_name' => 'Jane Doe',
                'customer_phone' => '08012345678',
                'expected_collection_date' => now()->addDays(7)->toDateString(),
                'products' => [[
                    'product_name' => $productType->name,
                    'grammage' => 100,
                    'cartons' => 3,
                    'pieces' => 4,
                    'quantity' => 64,
                    'price' => 1000,
                ]],
                'total_value' => 64000,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $record->refresh();

        $this->assertSame(64, $record->products[0]['quantity']);
        $this->assertSame(64000.0, (float) $record->total_value);
    }
}
