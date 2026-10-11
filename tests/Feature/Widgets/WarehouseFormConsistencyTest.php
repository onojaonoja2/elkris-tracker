<?php

namespace Tests\Feature\Widgets;

use App\Filament\Pages\ProductionDashboard;
use App\Filament\Pages\WarehouseManagerDashboard;
use App\Models\Inventory;
use App\Models\ProductType;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WarehouseFormConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatch_raw_material_form_autofills_current_warehouse(): void
    {
        $manager = User::factory()->warehouseManager()->create();
        $warehouse = Warehouse::factory()->create([
            'manager_id' => $manager->id,
            'has_raw_material_module' => true,
        ]);

        $this->actingAs($manager);

        Livewire::test(WarehouseManagerDashboard::class)
            ->mountAction('dispatchRawMaterial')
            ->assertSet('mountedActions.0.data.from_warehouse_id', $warehouse->id);
    }

    public function test_receive_raw_materials_form_autofills_current_warehouse(): void
    {
        $manager = User::factory()->warehouseManager()->create();
        $warehouse = Warehouse::factory()->create([
            'manager_id' => $manager->id,
            'has_raw_material_module' => true,
        ]);

        $this->actingAs($manager);

        Livewire::test(WarehouseManagerDashboard::class)
            ->mountAction('receiveRawMaterials')
            ->assertSet('mountedActions.0.data.warehouse_id', $warehouse->id);
    }

    public function test_raw_material_forms_fall_back_to_managed_warehouse_without_module(): void
    {
        $manager = User::factory()->warehouseManager()->create();
        $warehouse = Warehouse::factory()->create(['manager_id' => $manager->id]);

        $this->actingAs($manager);

        Livewire::test(WarehouseManagerDashboard::class)
            ->mountAction('dispatchRawMaterial')
            ->assertSet('mountedActions.0.data.from_warehouse_id', $warehouse->id);

        Livewire::test(WarehouseManagerDashboard::class)
            ->mountAction('receiveRawMaterials')
            ->assertSet('mountedActions.0.data.warehouse_id', $warehouse->id);
    }

    public function test_dispatch_stock_destination_options_exclude_source_warehouse(): void
    {
        $source = Warehouse::factory()->create(['name' => 'Source Warehouse']);
        $other = Warehouse::factory()->create(['name' => 'Other Warehouse']);
        $inactive = Warehouse::factory()->create(['name' => 'Inactive Warehouse', 'is_active' => false]);

        $options = WarehouseManagerDashboard::activeWarehousesExcluding($source->id);

        $this->assertArrayNotHasKey($source->id, $options);
        $this->assertArrayHasKey($other->id, $options);
        $this->assertArrayNotHasKey($inactive->id, $options);
    }

    public function test_raw_material_destination_options_require_module_and_exclude_source(): void
    {
        $source = Warehouse::factory()->create(['name' => 'Source Raw', 'has_raw_material_module' => true]);
        $moduleWarehouse = Warehouse::factory()->create(['name' => 'Module Warehouse', 'has_production_module' => true]);
        $plain = Warehouse::factory()->create(['name' => 'Plain Warehouse']);

        $options = WarehouseManagerDashboard::activeModuleWarehousesExcluding($source->id);

        $this->assertArrayNotHasKey($source->id, $options);
        $this->assertArrayHasKey($moduleWarehouse->id, $options);
        $this->assertArrayNotHasKey($plain->id, $options);
    }

    public function test_dispatch_stock_rejects_self_dispatch(): void
    {
        $manager = User::factory()->warehouseManager()->create();
        $warehouse = Warehouse::factory()->create(['manager_id' => $manager->id]);

        $productType = ProductType::factory()->create([
            'name' => 'Self Dispatch Product',
            'available_grammages' => [100],
        ]);

        Inventory::create([
            'warehouse_id' => $warehouse->id,
            'product_type_id' => $productType->id,
            'grammage' => 100,
            'quantity' => 50,
        ]);

        $this->actingAs($manager);

        $test = Livewire::test(WarehouseManagerDashboard::class)->mountAction('dispatchStock');

        $items = $test->get('mountedActions.0.data.items');
        $itemKey = array_key_first($items);

        $test->set('mountedActions.0.data.from_warehouse_id', $warehouse->id)
            ->set('mountedActions.0.data.to_type', 'warehouse')
            ->set('mountedActions.0.data.to_warehouse_id', $warehouse->id)
            ->set("mountedActions.0.data.items.{$itemKey}.product_type_id", $productType->id)
            ->set("mountedActions.0.data.items.{$itemKey}.grammage", '100')
            ->set("mountedActions.0.data.items.{$itemKey}.quantity", 5)
            ->callMountedAction()
            ->assertHasActionErrors();

        $this->assertDatabaseMissing('stock_transfers', ['from_warehouse_id' => $warehouse->id]);
        $this->assertSame(50, Inventory::where('warehouse_id', $warehouse->id)->first()->quantity);
    }

    public function test_production_dispatch_defaults_to_managed_production_warehouse(): void
    {
        $producer = User::factory()->productionManagement()->create();

        $managed = Warehouse::factory()->create([
            'name' => 'Managed Production',
            'manager_id' => $producer->id,
            'has_production_module' => true,
        ]);

        Warehouse::factory()->create([
            'name' => 'Unmanaged Production',
            'has_production_module' => true,
        ]);

        $this->actingAs($producer);

        Livewire::test(ProductionDashboard::class)
            ->mountAction('dispatchStock')
            ->assertSet('mountedActions.0.data.from_warehouse_id', $managed->id);
    }
}
