<?php

namespace Tests\Feature\Widgets;

use App\Filament\Pages\WarehouseManagerDashboard;
use App\Filament\Widgets\WarehouseDirectSalesWidget;
use App\Filament\Widgets\WarehouseDispatchRequestsWidget;
use App\Filament\Widgets\WarehouseStocksWidget;
use App\Models\Inventory;
use App\Models\ProductType;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WarehouseManagerAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_manager_assigned_as_manager_accesses_dashboard_scoped(): void
    {
        $production = User::factory()->productionManagement()->create();
        $otherManager = User::factory()->warehouseManager()->create();

        $mine = Warehouse::factory()->create(['manager_id' => $production->id, 'has_raw_material_module' => true]);
        $theirs = Warehouse::factory()->create(['manager_id' => $otherManager->id]);

        $productType = ProductType::factory()->create([
            'name' => 'Access Test Product',
            'available_grammages' => [100],
        ]);

        foreach ([$mine, $theirs] as $warehouse) {
            Inventory::factory()->create([
                'warehouse_id' => $warehouse->id,
                'product_type_id' => $productType->id,
                'grammage' => 100,
                'quantity' => 20,
            ]);
        }

        StockTransfer::factory()->create([
            'from_warehouse_id' => $mine->id,
            'to_agent_id' => $production->id,
            'status' => 'requested',
        ]);
        StockTransfer::factory()->create([
            'from_warehouse_id' => $theirs->id,
            'to_agent_id' => $otherManager->id,
            'status' => 'requested',
        ]);

        $this->assertTrue($production->managesWarehouses());
        $this->assertTrue($production->canManageWarehouses());

        $this->actingAs($production);

        Livewire::test(WarehouseManagerDashboard::class)->assertOk();

        Livewire::test(WarehouseDispatchRequestsWidget::class)
            ->assertSee('Pending Warehouse Dispatch Requests')
            ->assertSee($mine->name)
            ->assertDontSee($theirs->name);
    }

    public function test_production_manager_without_warehouse_cannot_access_dashboard(): void
    {
        $production = User::factory()->productionManagement()->create();

        $this->assertFalse($production->managesWarehouses());
        $this->assertFalse($production->canManageWarehouses());

        $this->actingAs($production);

        Livewire::test(WarehouseManagerDashboard::class)->assertRedirect();
        $this->assertFalse(WarehouseDirectSalesWidget::canView());
        $this->assertFalse(WarehouseStocksWidget::canView());
    }

    public function test_module_flags_and_scopes(): void
    {
        $plain = Warehouse::factory()->create(['type' => 'state']);
        $production = Warehouse::factory()->create(['type' => 'central', 'has_production_module' => true]);
        $raw = Warehouse::factory()->create(['type' => 'state', 'has_raw_material_module' => true]);
        $both = Warehouse::factory()->create(['type' => 'state', 'has_production_module' => true, 'has_raw_material_module' => true]);

        $this->assertFalse($plain->fresh()->isProductionStore());
        $this->assertTrue($production->fresh()->isProductionStore());
        $this->assertTrue($raw->fresh()->isRawMaterialStore());

        $this->assertEqualsCanonicalizing(
            [$production->id, $both->id],
            Warehouse::productionModuleIds()
        );
        $this->assertEqualsCanonicalizing(
            [$raw->id, $both->id],
            Warehouse::rawMaterialModuleIds()
        );
    }
}
