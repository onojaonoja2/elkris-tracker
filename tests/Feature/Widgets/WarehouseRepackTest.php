<?php

namespace Tests\Feature\Widgets;

use App\Filament\Pages\WarehouseManagerDashboard;
use App\Filament\Widgets\WarehouseRepacksWidget;
use App\Models\Inventory;
use App\Models\ProductType;
use App\Models\Repack;
use App\Models\StockTransaction;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\RepackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class WarehouseRepackTest extends TestCase
{
    use RefreshDatabase;

    private function setupScenario(int $bags = 10): array
    {
        $manager = User::factory()->warehouseManager()->create();
        $warehouse = Warehouse::factory()->create(['manager_id' => $manager->id]);

        $bulk = ProductType::factory()->create([
            'name' => 'Bulk Grain',
            'available_grammages' => [['grammage' => 5000, 'carton_quantity' => 1, 'packaging' => 'bag']],
        ]);

        $retail = ProductType::factory()->create([
            'name' => 'Retail Pack',
            'available_grammages' => [['grammage' => 650, 'carton_quantity' => 24]],
        ]);

        Inventory::factory()->create([
            'warehouse_id' => $warehouse->id,
            'product_type_id' => $bulk->id,
            'grammage' => 5000,
            'quantity' => $bags,
        ]);

        return [$manager, $warehouse, $bulk, $retail];
    }

    private function repackPayload(Warehouse $warehouse, ProductType $bulk, ProductType $retail, int $in = 2, int $out = 14): array
    {
        return [
            'warehouse_id' => $warehouse->id,
            'input_product_type_id' => $bulk->id,
            'input_grammage' => 5000,
            'input_quantity' => $in,
            'output_product_type_id' => $retail->id,
            'output_grammage' => 650,
            'output_quantity' => $out,
        ];
    }

    public function test_warehouse_manager_repacks_bags_into_skus(): void
    {
        [$manager, $warehouse, $bulk, $retail] = $this->setupScenario();

        $this->actingAs($manager);

        Livewire::test(WarehouseManagerDashboard::class)
            ->mountAction('recordRepack')
            ->fillForm($this->repackPayload($warehouse, $bulk, $retail))
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertSame(8, Inventory::where('warehouse_id', $warehouse->id)->where('product_type_id', $bulk->id)->first()->quantity);
        $this->assertSame(14, Inventory::where('warehouse_id', $warehouse->id)->where('product_type_id', $retail->id)->first()->quantity);

        $repack = Repack::firstOrFail();
        $this->assertSame('posted', $repack->status);
        $this->assertSame($manager->id, $repack->user_id);

        $this->assertSame(1, StockTransaction::where('disbursed_to', 'like', "Repack #{$repack->id}%")->where('type', 'disbursed')->count());
        $this->assertSame(1, StockTransaction::where('disbursed_to', 'like', "Repack #{$repack->id}%")->where('type', 'received')->count());

        Livewire::test(WarehouseRepacksWidget::class)
            ->assertSee('Bulk Grain')
            ->assertSee('Retail Pack');
    }

    public function test_repack_blocked_when_input_short_or_same_sku(): void
    {
        [$manager, $warehouse, $bulk, $retail] = $this->setupScenario(bags: 1);

        try {
            RepackService::post($this->repackPayload($warehouse, $bulk, $retail, in: 2, out: 14), $manager->id);
            $this->fail('Expected ValidationException for insufficient input stock.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('input_quantity', $e->errors());
        }

        try {
            RepackService::post([
                'warehouse_id' => $warehouse->id,
                'input_product_type_id' => $bulk->id,
                'input_grammage' => 5000,
                'input_quantity' => 1,
                'output_product_type_id' => $bulk->id,
                'output_grammage' => 5000,
                'output_quantity' => 1,
            ], $manager->id);
            $this->fail('Expected ValidationException for identical SKUs.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('output_grammage', $e->errors());
        }

        $this->assertSame(0, Repack::count());
        $this->assertSame(1, Inventory::where('warehouse_id', $warehouse->id)->where('product_type_id', $bulk->id)->first()->quantity);
    }
}
