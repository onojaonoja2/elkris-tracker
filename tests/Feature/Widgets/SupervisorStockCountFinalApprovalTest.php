<?php

namespace Tests\Feature\Widgets;

use App\Filament\Widgets\SupervisorStockCountFinalApprovalWidget;
use App\Models\ProductType;
use App\Models\StockCount;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SupervisorStockCountFinalApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_approving_warehouse_stock_count_updates_inventory(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $warehouse = Warehouse::factory()->create();
        $productType = ProductType::factory()->create(['available_grammages' => [100, 200]]);

        $stockCount = $this->pendingStockCount([
            'user_id' => User::factory()->warehouseManager()->create()->id,
            'warehouse_id' => $warehouse->id,
        ], $productType, 25);

        $this->actingAs($supervisor);

        Livewire::test(SupervisorStockCountFinalApprovalWidget::class)
            ->callTableAction('finalApprove', $stockCount->id);

        $this->assertDatabaseHas('stock_counts', [
            'id' => $stockCount->id,
            'status' => 'approved',
            'approved_by' => $supervisor->id,
        ]);

        $this->assertDatabaseHas('inventories', [
            'warehouse_id' => $warehouse->id,
            'product_type_id' => $productType->id,
            'grammage' => 100,
            'quantity' => 25,
        ]);
    }

    public function test_supervisor_approving_verified_csr_stock_count_updates_agent_stock(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $agent = User::factory()->communitySalesRepresentative()->create();
        $productType = ProductType::factory()->create(['available_grammages' => [100, 200]]);

        $stockCount = $this->pendingStockCount([
            'user_id' => $agent->id,
            'supervisor_status' => 'verified',
        ], $productType, 12);

        $this->actingAs($supervisor);

        Livewire::test(SupervisorStockCountFinalApprovalWidget::class)
            ->callTableAction('finalApprove', $stockCount->id);

        $this->assertDatabaseHas('agent_stocks', [
            'user_id' => $agent->id,
            'product_type_id' => $productType->id,
            'product_name' => $productType->name,
            'grammage' => 100,
            'quantity' => 12,
        ]);
    }

    public function test_pending_stock_counts_are_listed_newest_first(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $warehouse = Warehouse::factory()->create();
        $productType = ProductType::factory()->create(['available_grammages' => [100, 200]]);
        $warehouseManager = User::factory()->warehouseManager()->create();

        $older = $this->pendingStockCount([
            'user_id' => $warehouseManager->id,
            'warehouse_id' => $warehouse->id,
        ], $productType, 5);

        $newer = $this->pendingStockCount([
            'user_id' => $warehouseManager->id,
            'warehouse_id' => $warehouse->id,
        ], $productType, 10);

        $older->forceFill(['created_at' => now()->subHours(3)])->save();
        $newer->forceFill(['created_at' => now()])->save();

        $this->actingAs($supervisor);

        Livewire::test(SupervisorStockCountFinalApprovalWidget::class)
            ->assertCanSeeTableRecords([$newer, $older], inOrder: true);
    }

    public function test_supervisor_widget_includes_verifiable_counts_but_excludes_unverified_csr_count(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $warehouse = Warehouse::factory()->create(['name' => 'Main Warehouse']);
        $productType = ProductType::factory()->create(['available_grammages' => [100, 200]]);

        $warehouseManager = User::factory()->warehouseManager()->create(['name' => 'Wale Warehouse']);
        $verifiedCsr = User::factory()->communitySalesRepresentative()->create(['name' => 'Vera CSR']);
        $unverifiedCsr = User::factory()->communitySalesRepresentative()->create(['name' => 'Cara CSR']);

        $this->pendingStockCount([
            'user_id' => $warehouseManager->id,
            'warehouse_id' => $warehouse->id,
            'supervisor_status' => null,
        ], $productType, 25);

        $this->pendingStockCount([
            'user_id' => $verifiedCsr->id,
            'supervisor_status' => 'verified',
        ], $productType, 10);

        $this->pendingStockCount([
            'user_id' => $unverifiedCsr->id,
            'supervisor_status' => null,
        ], $productType, 5);

        $this->actingAs($supervisor);

        Livewire::test(SupervisorStockCountFinalApprovalWidget::class)
            ->assertSee('Wale Warehouse')
            ->assertSee('Vera CSR')
            ->assertDontSee('Cara CSR');
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
