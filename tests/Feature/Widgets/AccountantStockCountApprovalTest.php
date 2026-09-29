<?php

namespace Tests\Feature\Widgets;

use App\Filament\Widgets\AccountantStockCountApprovalWidget;
use App\Models\AgentStock;
use App\Models\Inventory;
use App\Models\ProductType;
use App\Models\StockCount;
use App\Models\StockTransaction;
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

    public function test_accountant_widget_includes_warehouse_count_but_excludes_unverified_csr_count(): void
    {
        $accountant = User::factory()->accountant()->create();
        $warehouse = Warehouse::factory()->create(['name' => 'Main Warehouse']);
        $productType = ProductType::factory()->create(['available_grammages' => [100, 200]]);

        $warehouseManager = User::factory()->warehouseManager()->create(['name' => 'Wale Warehouse']);
        $csr = User::factory()->communitySalesRepresentative()->create(['name' => 'Cara CSR']);

        $this->pendingStockCount([
            'user_id' => $warehouseManager->id,
            'warehouse_id' => $warehouse->id,
            'supervisor_status' => null,
        ], $productType, 25);

        $this->pendingStockCount([
            'user_id' => $csr->id,
            'supervisor_status' => null,
        ], $productType, 10);

        $this->actingAs($accountant);

        Livewire::test(AccountantStockCountApprovalWidget::class)
            ->assertSee('Wale Warehouse')
            ->assertDontSee('Cara CSR');
    }

    public function test_accountant_approving_warehouse_initial_stock_count_replaces_existing_inventory(): void
    {
        $accountant = User::factory()->accountant()->create();
        $warehouse = Warehouse::factory()->create();
        $productType = ProductType::factory()->create(['available_grammages' => [100, 200]]);

        Inventory::create([
            'warehouse_id' => $warehouse->id,
            'product_type_id' => $productType->id,
            'grammage' => 100,
            'quantity' => 50,
        ]);
        Inventory::create([
            'warehouse_id' => $warehouse->id,
            'product_type_id' => $productType->id,
            'grammage' => 200,
            'quantity' => 30,
        ]);

        $stockCount = $this->pendingStockCount([
            'user_id' => User::factory()->warehouseManager()->create()->id,
            'warehouse_id' => $warehouse->id,
        ], $productType, 20);

        $stockCount->items()->create([
            'product_type_id' => $productType->id,
            'product_name' => $productType->name,
            'grammage' => 200,
            'quantity' => 0,
        ]);

        $this->actingAs($accountant);

        Livewire::test(AccountantStockCountApprovalWidget::class)
            ->callTableAction('accountantApprove', $stockCount->id);

        $this->assertDatabaseHas('inventories', [
            'warehouse_id' => $warehouse->id,
            'product_type_id' => $productType->id,
            'grammage' => 100,
            'quantity' => 20,
        ]);

        $this->assertDatabaseHas('inventories', [
            'warehouse_id' => $warehouse->id,
            'product_type_id' => $productType->id,
            'grammage' => 200,
            'quantity' => 0,
        ]);

        $this->assertSame(
            2,
            Inventory::where('warehouse_id', $warehouse->id)->count()
        );
    }

    public function test_accountant_approving_warehouse_initial_stock_count_removes_uncounted_inventory(): void
    {
        $accountant = User::factory()->accountant()->create();
        $warehouse = Warehouse::factory()->create();
        $countedType = ProductType::factory()->create(['available_grammages' => [100, 200]]);
        $uncountedType = ProductType::factory()->create([
            'name' => 'Uncounted Product',
            'available_grammages' => [100, 200],
        ]);

        Inventory::create([
            'warehouse_id' => $warehouse->id,
            'product_type_id' => $uncountedType->id,
            'grammage' => 100,
            'quantity' => 45,
        ]);

        $stockCount = $this->pendingStockCount([
            'user_id' => User::factory()->warehouseManager()->create()->id,
            'warehouse_id' => $warehouse->id,
        ], $countedType, 12);

        $this->actingAs($accountant);

        Livewire::test(AccountantStockCountApprovalWidget::class)
            ->callTableAction('accountantApprove', $stockCount->id);

        $this->assertDatabaseMissing('inventories', [
            'warehouse_id' => $warehouse->id,
            'product_type_id' => $uncountedType->id,
            'grammage' => 100,
        ]);

        $this->assertDatabaseHas('inventories', [
            'warehouse_id' => $warehouse->id,
            'product_type_id' => $countedType->id,
            'grammage' => 100,
            'quantity' => 12,
        ]);
    }

    public function test_accountant_approving_warehouse_additional_stock_count_increments_once_and_records_transaction(): void
    {
        $accountant = User::factory()->accountant()->create();
        $warehouse = Warehouse::factory()->create();
        $productType = ProductType::factory()->create(['available_grammages' => [100, 200]]);

        Inventory::create([
            'warehouse_id' => $warehouse->id,
            'product_type_id' => $productType->id,
            'grammage' => 100,
            'quantity' => 10,
        ]);

        $stockCount = $this->pendingStockCount([
            'user_id' => User::factory()->warehouseManager()->create()->id,
            'warehouse_id' => $warehouse->id,
            'is_additional_count' => true,
        ], $productType, 5);

        $this->actingAs($accountant);

        Livewire::test(AccountantStockCountApprovalWidget::class)
            ->callTableAction('accountantApprove', $stockCount->id);

        $this->assertDatabaseHas('inventories', [
            'warehouse_id' => $warehouse->id,
            'product_type_id' => $productType->id,
            'grammage' => 100,
            'quantity' => 15,
        ]);

        $this->assertDatabaseHas('stock_transactions', [
            'type' => 'received',
            'product_type_id' => $productType->id,
            'grammage' => 100,
            'quantity' => 5,
            'warehouse_id' => $warehouse->id,
            'disbursed_to' => 'Additional stock count #'.$stockCount->id,
        ]);

        $this->assertSame(
            1,
            StockTransaction::where('disbursed_to', 'Additional stock count #'.$stockCount->id)->count()
        );
    }

    public function test_accountant_approving_agent_additional_stock_count_increments_once_and_records_transaction(): void
    {
        $accountant = User::factory()->accountant()->create();
        $agent = User::factory()->communitySalesRepresentative()->create();
        $productType = ProductType::factory()->create(['available_grammages' => [100, 200]]);

        AgentStock::create([
            'user_id' => $agent->id,
            'product_type_id' => $productType->id,
            'product_name' => $productType->name,
            'grammage' => 100,
            'quantity' => 8,
        ]);

        $stockCount = $this->pendingStockCount([
            'user_id' => $agent->id,
            'is_additional_count' => true,
        ], $productType, 4);

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

        $this->assertDatabaseHas('stock_transactions', [
            'type' => 'received',
            'product_type_id' => $productType->id,
            'quantity' => 4,
            'user_id' => $agent->id,
            'disbursed_to' => 'Additional stock count #'.$stockCount->id,
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
