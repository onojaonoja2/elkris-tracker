<?php

namespace Tests\Feature\Widgets;

use App\Filament\Widgets\AccountantStockCountApprovalWidget;
use App\Models\ProductType;
use App\Models\StockCount;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AccountantStockCountApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_accountant_approving_warehouse_stock_count_updates_inventory(): void
    {
        $accountant = User::factory()->accountant()->create();
        $warehouse = Warehouse::factory()->create();
        $productType = ProductType::factory()->create(['available_grammages' => [100, 200]]);

        $stockCount = $this->pendingStockCount([
            'user_id' => User::factory()->warehouseManager()->create()->id,
            'warehouse_id' => $warehouse->id,
        ], $productType, 25);

        $this->actingAs($accountant);

        Livewire::test(AccountantStockCountApprovalWidget::class)
            ->callTableAction('accountantApprove', $stockCount->id);

        $this->assertDatabaseHas('stock_counts', [
            'id' => $stockCount->id,
            'status' => 'approved',
            'approved_by' => $accountant->id,
        ]);

        $this->assertDatabaseHas('inventories', [
            'warehouse_id' => $warehouse->id,
            'product_type_id' => $productType->id,
            'grammage' => 100,
            'quantity' => 25,
        ]);
    }

    public function test_accountant_approving_agent_stock_count_updates_agent_stock(): void
    {
        $accountant = User::factory()->accountant()->create();
        $agent = User::factory()->communitySalesRepresentative()->create();
        $productType = ProductType::factory()->create(['available_grammages' => [100, 200]]);

        $stockCount = $this->pendingStockCount([
            'user_id' => $agent->id,
        ], $productType, 12);

        $this->actingAs($accountant);

        Livewire::test(AccountantStockCountApprovalWidget::class)
            ->callTableAction('accountantApprove', $stockCount->id);

        $this->assertDatabaseHas('agent_stocks', [
            'user_id' => $agent->id,
            'product_type_id' => $productType->id,
            'product_name' => $productType->name,
            'grammage' => 100,
            'quantity' => 12,
        ]);
    }

    private function pendingStockCount(array $attributes, ProductType $productType, int $quantity): StockCount
    {
        $stockCount = StockCount::create(array_merge([
            'is_additional_count' => false,
            'status' => 'pending',
            'supervisor_status' => 'verified',
        ], $attributes));

        $stockCount->items()->create([
            'product_type_id' => $productType->id,
            'product_name' => $productType->name,
            'grammage' => 100,
            'quantity' => $quantity,
        ]);

        return $stockCount;
    }
}
