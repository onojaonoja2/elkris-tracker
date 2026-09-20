<?php

namespace Tests\Feature\Production;

use App\Filament\Pages\ManagerDashboard;
use App\Filament\Pages\ProductionDashboard;
use App\Filament\Resources\ProductionRuns\Pages\ManageProductionRuns;
use App\Filament\Widgets\ProductionOutgoingTransfersWidget;
use App\Filament\Widgets\ProductionRawMaterialsWidget;
use App\Filament\Widgets\ProductionRunsWidget;
use App\Filament\Widgets\ProductionStoreStockWidget;
use App\Models\Inventory;
use App\Models\ProductionRun;
use App\Models\ProductType;
use App\Models\RawMaterial;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ProductionRunService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class ProductionWarehouseTest extends TestCase
{
    use RefreshDatabase;

    private function productionStore(): Warehouse
    {
        return Warehouse::factory()->create(['type' => 'production', 'name' => 'Production Store']);
    }

    private function productType(): ProductType
    {
        return ProductType::factory()->create([
            'name' => fake()->unique()->word(),
            'available_grammages' => [
                ['grammage' => 100, 'carton_quantity' => 20],
            ],
        ]);
    }

    private function dispatchFromStore(
        ?int $warehouseId,
        ?int $agentId,
        int $productTypeId,
        int $quantity,
        string $toType = 'warehouse',
    ): Testable {
        $test = Livewire::test(ProductionDashboard::class)->mountAction('dispatchStock');

        $items = $test->get('mountedActions.0.data.items');
        $itemKey = array_key_first($items);

        $test->set('mountedActions.0.data.to_type', $toType);

        if ($toType === 'warehouse') {
            $test->set('mountedActions.0.data.to_warehouse_id', $warehouseId);
        } else {
            $test->set('mountedActions.0.data.to_agent_id', $agentId);
        }

        return $test
            ->set("mountedActions.0.data.items.{$itemKey}.product_type_id", $productTypeId)
            ->set("mountedActions.0.data.items.{$itemKey}.grammage", '100')
            ->set("mountedActions.0.data.items.{$itemKey}.quantity", $quantity)
            ->callMountedAction();
    }

    private function mappedRun(User $creator, ProductType $productType, array $overrides = []): ProductionRun
    {
        $material = RawMaterial::factory()->create(['quantity' => 1000, 'unit_of_measure' => 'kg']);

        return ProductionRunService::create(array_merge([
            'raw_materials' => [
                ['raw_material_id' => $material->id, 'quantity_used' => 10],
            ],
            'production_date' => now()->format('Y-m-d'),
            'output_name' => $productType->name,
            'output_quantity' => 50,
            'output_unit' => 'pieces',
            'product_type_id' => $productType->id,
            'grammage' => 100,
            'finished_quantity' => 50,
            'created_by' => $creator->id,
        ], $overrides));
    }

    public function test_only_one_production_warehouse_can_be_designated(): void
    {
        $first = Warehouse::factory()->create(['type' => 'production']);
        $this->assertSame($first->id, Warehouse::productionStore()->id);

        $second = Warehouse::factory()->create(['type' => 'state']);
        $second->update(['type' => 'production']);

        $this->assertSame($second->id, Warehouse::productionStore()->id);
        $this->assertSame('state', $first->fresh()->type);
    }

    public function test_review_posts_finished_goods_to_production_store(): void
    {
        $producer = User::factory()->productionManagement()->create();
        $accountant = User::factory()->accountant()->create();
        $store = $this->productionStore();
        $productType = $this->productType();

        $run = $this->mappedRun($producer, $productType);

        ProductionRunService::review($run, ['status' => 'reviewed'], $accountant->id);

        $this->assertDatabaseHas('inventories', [
            'warehouse_id' => $store->id,
            'product_type_id' => $productType->id,
            'grammage' => 100,
            'quantity' => 50,
        ]);
        $this->assertDatabaseHas('stock_transactions', [
            'type' => 'received',
            'product_type_id' => $productType->id,
            'quantity' => 50,
            'warehouse_id' => $store->id,
        ]);
        $this->assertTrue($run->fresh()->isLocked());
    }

    public function test_flagged_run_does_not_post_stock(): void
    {
        $producer = User::factory()->productionManagement()->create();
        $accountant = User::factory()->accountant()->create();
        $this->productionStore();
        $run = $this->mappedRun($producer, $this->productType());

        ProductionRunService::review($run, ['status' => 'flagged'], $accountant->id);

        $this->assertSame('flagged', $run->fresh()->status);
        $this->assertSame(0, Inventory::count());
        $this->assertDatabaseMissing('stock_transactions', ['type' => 'received']);
    }

    public function test_legacy_run_without_mapping_posts_nothing(): void
    {
        $producer = User::factory()->productionManagement()->create();
        $accountant = User::factory()->accountant()->create();
        $this->productionStore();

        $material = RawMaterial::factory()->create(['quantity' => 1000, 'unit_of_measure' => 'kg']);
        $run = ProductionRunService::create([
            'raw_materials' => [['raw_material_id' => $material->id, 'quantity_used' => 10]],
            'production_date' => now()->format('Y-m-d'),
            'output_name' => 'Legacy Output',
            'output_quantity' => 20,
            'output_unit' => 'bags',
            'created_by' => $producer->id,
        ]);

        ProductionRunService::review($run, ['status' => 'reviewed'], $accountant->id);

        $this->assertSame('reviewed', $run->fresh()->status);
        $this->assertSame(0, Inventory::count());
    }

    public function test_review_without_designated_store_fails(): void
    {
        $producer = User::factory()->productionManagement()->create();
        $accountant = User::factory()->accountant()->create();
        $run = $this->mappedRun($producer, $this->productType());

        try {
            ProductionRunService::review($run, ['status' => 'reviewed'], $accountant->id);
            $this->fail('Expected a ValidationException when no production store exists.');
        } catch (ValidationException $e) {
            $this->assertSame('pending_review', $run->fresh()->status);
            $this->assertSame(0, Inventory::count());
        }
    }

    public function test_reviewed_run_cannot_post_twice(): void
    {
        $producer = User::factory()->productionManagement()->create();
        $accountant = User::factory()->accountant()->create();
        $store = $this->productionStore();
        $productType = $this->productType();
        $run = $this->mappedRun($producer, $productType);

        ProductionRunService::review($run, ['status' => 'reviewed'], $accountant->id);

        try {
            ProductionRunService::review($run, ['status' => 'reviewed'], $accountant->id);
            $this->fail('Expected a ValidationException on double review.');
        } catch (ValidationException $e) {
            $this->assertSame(
                50,
                Inventory::where('warehouse_id', $store->id)
                    ->where('product_type_id', $productType->id)
                    ->value('quantity')
            );
        }
    }

    public function test_production_manager_can_dispatch_to_warehouse(): void
    {
        $producer = User::factory()->productionManagement()->create();
        $store = $this->productionStore();
        $destination = Warehouse::factory()->create(['type' => 'state']);
        $productType = $this->productType();

        Inventory::create([
            'warehouse_id' => $store->id,
            'product_type_id' => $productType->id,
            'grammage' => 100,
            'quantity' => 100,
        ]);

        $this->actingAs($producer);

        $this->dispatchFromStore($destination->id, null, $productType->id, 30)
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('stock_transfers', [
            'from_warehouse_id' => $store->id,
            'to_warehouse_id' => $destination->id,
            'source_type' => 'production_dispatch',
            'status' => 'dispatched',
            'dispatched_by' => $producer->id,
        ]);
        $this->assertSame(70, Inventory::where('warehouse_id', $store->id)->value('quantity'));
        $this->assertDatabaseHas('stock_transactions', [
            'type' => 'disbursed',
            'product_type_id' => $productType->id,
            'quantity' => 30,
            'warehouse_id' => $store->id,
        ]);
    }

    public function test_production_manager_can_dispatch_to_agent(): void
    {
        $producer = User::factory()->productionManagement()->create();
        $store = $this->productionStore();
        $csr = User::factory()->communitySalesRepresentative()->create();
        $productType = $this->productType();

        Inventory::create([
            'warehouse_id' => $store->id,
            'product_type_id' => $productType->id,
            'grammage' => 100,
            'quantity' => 100,
        ]);

        $this->actingAs($producer);

        $this->dispatchFromStore(null, $csr->id, $productType->id, 40, 'community_sales_representative')
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('stock_transfers', [
            'from_warehouse_id' => $store->id,
            'to_agent_id' => $csr->id,
            'source_type' => 'production_dispatch',
            'status' => 'dispatched',
        ]);
        $this->assertSame(60, Inventory::where('warehouse_id', $store->id)->value('quantity'));
    }

    public function test_dispatch_fails_with_insufficient_stock(): void
    {
        $producer = User::factory()->productionManagement()->create();
        $store = $this->productionStore();
        $destination = Warehouse::factory()->create(['type' => 'state']);
        $productType = $this->productType();

        Inventory::create([
            'warehouse_id' => $store->id,
            'product_type_id' => $productType->id,
            'grammage' => 100,
            'quantity' => 5,
        ]);

        $this->actingAs($producer);

        $this->dispatchFromStore($destination->id, null, $productType->id, 50)
            ->assertNotified('Dispatch failed');

        $this->assertDatabaseMissing('stock_transfers', ['from_warehouse_id' => $store->id]);
        $this->assertSame(5, Inventory::where('warehouse_id', $store->id)->value('quantity'));
    }

    public function test_production_dashboard_renders_widgets_and_dispatch_action(): void
    {
        $producer = User::factory()->productionManagement()->create();
        $this->actingAs($producer);

        $this->get('/admin/production-dashboard')->assertOk();

        Livewire::test(ProductionDashboard::class)
            ->assertActionExists('dispatchStock');

        Livewire::test(ProductionRunsWidget::class)->assertSee('Items Produced');
        Livewire::test(ProductionRawMaterialsWidget::class)->assertSee('Raw Materials');
        Livewire::test(ProductionStoreStockWidget::class)->assertSee('Production Store Stock');
        Livewire::test(ProductionOutgoingTransfersWidget::class)->assertSee('Production Dispatches');
    }

    public function test_manager_sees_production_widgets_without_dispatch_action(): void
    {
        $manager = User::factory()->manager()->create();
        $this->actingAs($manager);

        Livewire::test(ManagerDashboard::class)
            ->assertActionDoesNotExist('dispatchStock');

        Livewire::test(ProductionStoreStockWidget::class)->assertSee('Production Store Stock');
        Livewire::test(ProductionOutgoingTransfersWidget::class)->assertSee('Production Dispatches');
    }

    public function test_run_form_includes_product_mapping_fields(): void
    {
        $producer = User::factory()->productionManagement()->create();
        $this->actingAs($producer);

        Livewire::test(ManageProductionRuns::class)
            ->assertFormFieldVisible('product_type_id')
            ->assertFormFieldVisible('grammage')
            ->assertFormFieldVisible('finished_quantity');
    }
}
