<?php

namespace Tests\Feature\Production;

use App\Filament\Pages\WarehouseManagerDashboard;
use App\Filament\Widgets\ProductionIncomingTransfersWidget;
use App\Models\RawMaterial;
use App\Models\RawMaterialStock;
use App\Models\RawMaterialTransfer;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ProductionRunService;
use App\Services\RawMaterialTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class RawMaterialWarehouseTest extends TestCase
{
    use RefreshDatabase;

    private function setupScenario(float $quantity = 1000): array
    {
        $manager = User::factory()->warehouseManager()->create();
        $production = User::factory()->productionManagement()->create();

        $store = Warehouse::factory()->create(['has_production_module' => true, 'is_active' => true]);
        $rawWarehouse = Warehouse::factory()->create([
            'has_raw_material_module' => true,
            'is_active' => true,
            'manager_id' => $manager->id,
        ]);

        $material = RawMaterial::factory()->create([
            'name' => 'Test Grain',
            'unit_of_measure' => 'kg',
            'quantity' => $quantity,
        ]);

        RawMaterialStock::create([
            'warehouse_id' => $rawWarehouse->id,
            'raw_material_id' => $material->id,
            'quantity' => $quantity,
        ]);

        return [$manager, $production, $store, $rawWarehouse, $material];
    }

    public function test_warehouse_manager_dispatches_and_production_receives(): void
    {
        [$manager, $production, $store, $rawWarehouse, $material] = $this->setupScenario();

        $this->actingAs($manager);

        Livewire::test(WarehouseManagerDashboard::class)
            ->mountAction('dispatchRawMaterial')
            ->fillForm([
                'from_warehouse_id' => $rawWarehouse->id,
                'raw_material_id' => $material->id,
                'quantity' => 200,
                'to_warehouse_id' => $store->id,
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $transfer = RawMaterialTransfer::firstOrFail();
        $this->assertSame('dispatched', $transfer->status);
        $this->assertSame(800.0, (float) RawMaterialStock::where('warehouse_id', $rawWarehouse->id)->first()->quantity);

        $this->actingAs($production);

        Livewire::test(ProductionIncomingTransfersWidget::class)
            ->assertSee('Test Grain')
            ->callTableAction('receive', $transfer->id)
            ->assertHasNoTableActionErrors();

        $this->assertSame('received', $transfer->fresh()->status);
        $this->assertSame(200.0, (float) RawMaterialStock::where('warehouse_id', $store->id)->first()->quantity);
        // Global running total is unchanged by the move.
        $this->assertSame(1000.0, (float) $material->fresh()->quantity);
    }

    public function test_dispatch_blocked_when_source_short(): void
    {
        [$manager, $production, $store, $rawWarehouse, $material] = $this->setupScenario(quantity: 50);

        $this->expectException(ValidationException::class);

        RawMaterialTransferService::requestTransfer($rawWarehouse->id, $store->id, $material->id, 500, $manager->id);
    }

    public function test_production_run_consumes_from_production_warehouse(): void
    {
        [$manager, $production, $store, $rawWarehouse, $material] = $this->setupScenario();

        RawMaterialTransferService::receiveTransfer(
            tap(RawMaterialTransferService::requestTransfer($rawWarehouse->id, $store->id, $material->id, 400, $manager->id),
                fn (RawMaterialTransfer $t) => RawMaterialTransferService::dispatchTransfer($t, $manager->id)),
            $production->id
        );

        $run = ProductionRunService::create([
            'production_date' => now()->toDateString(),
            'output_name' => 'Test Output',
            'output_quantity' => 10,
            'output_unit' => 'pcs',
            'created_by' => $production->id,
            'raw_materials' => [
                ['raw_material_id' => $material->id, 'quantity_used' => 150],
            ],
        ]);

        $this->assertSame(250.0, (float) RawMaterialStock::where('warehouse_id', $store->id)->first()->quantity);
        $this->assertSame(850.0, (float) $material->fresh()->quantity);

        // Updating the run restores then re-deducts from the same scoped balance.
        ProductionRunService::update($run, [
            'output_name' => 'Test Output',
            'raw_materials' => [
                ['raw_material_id' => $material->id, 'quantity_used' => 100],
            ],
        ]);

        $this->assertSame(300.0, (float) RawMaterialStock::where('warehouse_id', $store->id)->first()->quantity);
    }

    public function test_production_run_blocked_when_production_warehouse_short(): void
    {
        [$manager, $production, $store, $rawWarehouse, $material] = $this->setupScenario();

        try {
            ProductionRunService::create([
                'production_date' => now()->toDateString(),
                'output_name' => 'Test Output',
                'output_quantity' => 10,
                'output_unit' => 'pcs',
                'created_by' => $production->id,
                'raw_materials' => [
                    ['raw_material_id' => $material->id, 'quantity_used' => 100],
                ],
            ]);
            $this->fail('Expected ValidationException for insufficient scoped stock.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString($store->name, $e->getMessage());
        }
    }

    public function test_production_run_falls_back_to_global_pool_without_production_warehouse(): void
    {
        $production = User::factory()->productionManagement()->create();
        $material = RawMaterial::factory()->create(['quantity' => 500]);

        ProductionRunService::create([
            'production_date' => now()->toDateString(),
            'output_name' => 'Legacy Output',
            'output_quantity' => 5,
            'output_unit' => 'pcs',
            'created_by' => $production->id,
            'raw_materials' => [
                ['raw_material_id' => $material->id, 'quantity_used' => 120],
            ],
        ]);

        $this->assertSame(380.0, (float) $material->fresh()->quantity);
        $this->assertSame(0, RawMaterialStock::count());
    }
}
