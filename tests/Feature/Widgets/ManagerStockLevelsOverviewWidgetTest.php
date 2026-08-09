<?php

namespace Tests\Feature\Widgets;

use App\Filament\Widgets\ManagerStockLevelsOverviewWidget;
use App\Models\AgentStock;
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

    public function test_stock_level_rows_expose_carton_quantities(): void
    {
        $admin = User::factory()->admin()->create();
        $productType = ProductType::factory()->create([
            'available_grammages' => [
                ['grammage' => 100, 'carton_quantity' => 20],
                200,
                500,
            ],
        ]);
        $warehouse = $this->centralWarehouse();

        Inventory::create([
            'warehouse_id' => $warehouse->id,
            'product_type_id' => $productType->id,
            'grammage' => 100,
            'quantity' => 43,
        ]);

        $this->actingAs($admin);

        $component = Livewire::test(ManagerStockLevelsOverviewWidget::class);
        $stockLevels = $component->instance()->getStockLevels();

        $row = $stockLevels['Central Warehouse']->first();

        $this->assertSame(20, $row->carton_quantity);
        $this->assertSame(2, $row->cartons);
        $this->assertSame(3, $row->remaining_pieces);
        $this->assertSame(43, $row->quantity);
    }

    public function test_agent_stock_rows_expose_carton_quantities(): void
    {
        $admin = User::factory()->admin()->create();
        $productType = ProductType::factory()->create([
            'available_grammages' => [
                ['grammage' => 100, 'carton_quantity' => 20],
                200,
                500,
            ],
        ]);
        $agent = User::factory()->sales()->create();

        AgentStock::create([
            'user_id' => $agent->id,
            'product_type_id' => $productType->id,
            'product_name' => $productType->name,
            'grammage' => 100,
            'quantity' => 25,
        ]);

        $this->actingAs($admin);

        $component = Livewire::test(ManagerStockLevelsOverviewWidget::class);
        $stockLevels = $component->instance()->getStockLevels();

        $row = $stockLevels['Community Sales Reps']->first()->first()->first();

        $this->assertSame(20, $row->carton_quantity);
        $this->assertSame(1, $row->cartons);
        $this->assertSame(5, $row->remaining_pieces);
    }

    public function test_carton_quantities_are_rendered_in_the_widget(): void
    {
        $admin = User::factory()->admin()->create();
        $productType = ProductType::factory()->create([
            'available_grammages' => [
                ['grammage' => 100, 'carton_quantity' => 20],
                200,
                500,
            ],
        ]);
        $warehouse = $this->centralWarehouse();

        Inventory::create([
            'warehouse_id' => $warehouse->id,
            'product_type_id' => $productType->id,
            'grammage' => 100,
            'quantity' => 43,
        ]);

        $this->actingAs($admin);

        Livewire::test(ManagerStockLevelsOverviewWidget::class)
            ->assertSee('2 ctns + 3 pcs');
    }
}
