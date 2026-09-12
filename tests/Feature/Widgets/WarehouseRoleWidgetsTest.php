<?php

namespace Tests\Feature\Widgets;

use App\Filament\Widgets\WarehouseCsrStockWidget;
use App\Filament\Widgets\WarehouseStocksWidget;
use App\Models\AgentStock;
use App\Models\Inventory;
use App\Models\ProductType;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WarehouseRoleWidgetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_warehouse_manager_sees_csr_stock_summary(): void
    {
        $manager = User::factory()->warehouseManager()->create();
        $csr = User::factory()->communitySalesRepresentative()->create(['name' => 'Mina CSR']);
        $productType = ProductType::factory()->create(['name' => 'Ora herbal mix']);

        AgentStock::factory()->create([
            'user_id' => $csr->id,
            'product_type_id' => $productType->id,
            'product_name' => $productType->name,
            'grammage' => 100,
            'quantity' => 30,
        ]);
        AgentStock::factory()->create([
            'user_id' => $csr->id,
            'product_type_id' => $productType->id,
            'product_name' => $productType->name,
            'grammage' => 500,
            'quantity' => 20,
        ]);

        $this->actingAs($manager);

        Livewire::test(WarehouseCsrStockWidget::class)
            ->assertCanSeeTableRecords([$csr])
            ->assertTableColumnStateSet('stock_units', 50, $csr)
            ->assertTableColumnStateSet('product_lines', 2, $csr)
            ->assertTableActionExists('viewBreakdown')
            ->assertTableActionExists('exportCsrStock');
    }

    public function test_warehouse_manager_can_open_csr_stock_breakdown_modal(): void
    {
        $manager = User::factory()->warehouseManager()->create();
        $csr = User::factory()->communitySalesRepresentative()->create(['name' => 'Mina CSR']);
        $productType = ProductType::factory()->create(['name' => 'Ora herbal mix']);

        AgentStock::factory()->create([
            'user_id' => $csr->id,
            'product_type_id' => $productType->id,
            'product_name' => $productType->name,
            'grammage' => 100,
            'quantity' => 30,
        ]);

        $this->actingAs($manager);

        Livewire::test(WarehouseCsrStockWidget::class)
            ->callTableAction('viewBreakdown', $csr->id)
            ->assertHasNoTableActionErrors();
    }

    public function test_warehouse_manager_sees_stock_from_all_warehouses(): void
    {
        $manager = User::factory()->warehouseManager()->create();
        Warehouse::factory()->create(['name' => 'Managed Warehouse']);
        $otherWarehouse = Warehouse::factory()->create(['name' => 'Remote Warehouse']);

        $productType = ProductType::factory()->create(['name' => 'Ora herbal mix']);
        $productType->update(['available_grammages' => [['grammage' => 100, 'carton_quantity' => 10]]]);

        $remote = Inventory::create([
            'warehouse_id' => $otherWarehouse->id,
            'product_type_id' => $productType->id,
            'grammage' => 100,
            'quantity' => 25,
        ]);

        $this->actingAs($manager);

        Livewire::test(WarehouseStocksWidget::class)
            ->assertCanSeeTableRecords([$remote])
            ->assertTableColumnStateSet('full_cartons', 2, $remote)
            ->assertTableColumnStateSet('remaining_pieces', 5, $remote)
            ->assertTableActionExists('viewMovements');
    }

    public function test_warehouse_manager_dispatch_opens_stock_movement_breakdown(): void
    {
        $manager = User::factory()->warehouseManager()->create();
        $warehouse = Warehouse::factory()->create(['name' => 'Remote Warehouse']);

        $productType = ProductType::factory()->create(['name' => 'Ora herbal mix']);
        $inventory = Inventory::create([
            'warehouse_id' => $warehouse->id,
            'product_type_id' => $productType->id,
            'grammage' => 100,
            'quantity' => 5,
        ]);

        $this->actingAs($manager);

        Livewire::test(WarehouseStocksWidget::class)
            ->callTableAction('viewMovements', $inventory->id)
            ->assertDispatched('open-stock-movement-breakdown');
    }
}
