<?php

namespace Tests\Feature\Widgets;

use App\Filament\Widgets\ManagerStockLevelsOverviewWidget;
use App\Models\Inventory;
use App\Models\ProductType;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManagerStockLevelsOverviewWidgetTest extends TestCase
{
    use RefreshDatabase;

    private function centralWarehouse(): Warehouse
    {
        return Warehouse::factory()->create(['type' => 'central']);
    }

    private function productType(): ProductType
    {
        return ProductType::factory()->create([
            'available_grammages' => [
                ['grammage' => 100, 'carton_quantity' => 20],
                200,
                500,
            ],
        ]);
    }

    public function test_warehouse_stock_rows_expose_carton_quantities(): void
    {
        $admin = User::factory()->admin()->create();
        $productType = $this->productType();
        $warehouse = $this->centralWarehouse();

        $inventory = Inventory::create([
            'warehouse_id' => $warehouse->id,
            'product_type_id' => $productType->id,
            'grammage' => 100,
            'quantity' => 43,
        ]);

        $this->actingAs($admin);

        Livewire::test(ManagerStockLevelsOverviewWidget::class)
            ->assertCanSeeTableRecords([$inventory])
            ->assertSee('2 ctns + 3 pcs');
    }

    public function test_stock_table_paginates_five_per_page(): void
    {
        $admin = User::factory()->admin()->create();
        $productType = $this->productType();
        $warehouse = $this->centralWarehouse();

        $records = collect();
        for ($i = 1; $i <= 6; $i++) {
            $records->push(Inventory::create([
                'warehouse_id' => $warehouse->id,
                'product_type_id' => $productType->id,
                'grammage' => $i * 100,
                'quantity' => $i,
            ]));
        }

        $this->actingAs($admin);

        // Default sort is quantity desc: quantities 6..2 on page one, quantity 1 on page two.
        Livewire::test(ManagerStockLevelsOverviewWidget::class)
            ->assertCanSeeTableRecords($records->sortByDesc('quantity')->take(5)->values())
            ->assertCanNotSeeTableRecords($records->sortByDesc('quantity')->skip(5)->values());
    }

    public function test_stock_view_action_shows_line_details(): void
    {
        $admin = User::factory()->admin()->create();
        $productType = $this->productType();
        $warehouse = $this->centralWarehouse();

        $inventory = Inventory::create([
            'warehouse_id' => $warehouse->id,
            'product_type_id' => $productType->id,
            'grammage' => 100,
            'quantity' => 43,
        ]);

        $this->actingAs($admin);

        Livewire::test(ManagerStockLevelsOverviewWidget::class)
            ->mountTableAction('view', $inventory->id)
            ->assertMountedActionModalSee('Central Warehouse')
            ->assertMountedActionModalSee('2 ctns + 3 pcs');
    }

    public function test_stock_table_exports_csv(): void
    {
        $admin = User::factory()->admin()->create();
        $productType = $this->productType();
        $warehouse = $this->centralWarehouse();

        Inventory::create([
            'warehouse_id' => $warehouse->id,
            'product_type_id' => $productType->id,
            'grammage' => 100,
            'quantity' => 43,
        ]);

        $this->actingAs($admin);

        Livewire::test(ManagerStockLevelsOverviewWidget::class)
            ->callTableAction('export')
            ->assertFileDownloaded();
    }
}
