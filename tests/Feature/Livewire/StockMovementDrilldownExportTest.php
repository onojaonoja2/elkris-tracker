<?php

namespace Tests\Feature\Livewire;

use App\Filament\Pages\ManagerDashboard;
use App\Filament\Widgets\ManagerStatsWidget;
use App\Livewire\StockEntityBreakdownTable;
use App\Livewire\StockMovementBreakdownTable;
use App\Models\ProductType;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StockMovementDrilldownExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_open_stock_movement_drilldown_from_dashboard(): void
    {
        $manager = User::factory()->manager()->create();
        $this->actingAs($manager);

        Livewire::test(ManagerDashboard::class)
            ->call('openStockMovementBreakdown')
            ->assertSet('breakdownEntityType', null)
            ->assertSet('breakdownEntityId', null)
            ->assertActionMounted('stockMovementBreakdown');
    }

    public function test_stock_transfers_stat_card_dispatches_drilldown(): void
    {
        $manager = User::factory()->manager()->create();
        $this->actingAs($manager);

        Livewire::test(ManagerStatsWidget::class)
            ->assertSee('Stock Transfers')
            ->assertSee('open-stock-movement-breakdown', false);
    }

    public function test_manager_can_view_entity_and_movement_tables(): void
    {
        $manager = User::factory()->manager()->create();
        $warehouse = Warehouse::factory()->create();
        $this->actingAs($manager);

        Livewire::test(StockEntityBreakdownTable::class)
            ->assertOk();

        Livewire::test(StockMovementBreakdownTable::class, [
            'entityType' => 'warehouse',
            'entityId' => $warehouse->id,
        ])
            ->assertOk()
            ->assertSee('Export');
    }

    public function test_export_respects_entity_and_period_filter(): void
    {
        $manager = User::factory()->manager()->create();
        $this->actingAs($manager);

        session()->put('dashboard_date_from', now()->subDays(7)->toDateTimeString());
        session()->put('dashboard_date_to', now()->toDateTimeString());

        $warehouse = Warehouse::factory()->create(['name' => 'Export Warehouse']);
        $otherWarehouse = Warehouse::factory()->create(['name' => 'Other Warehouse']);
        $product = ProductType::factory()->create(['name' => 'Export Test Product']);

        $included = $this->createTransfer($warehouse->id, $product->id, now()->subDays(2));
        $excludedTransfer = $this->createTransfer($otherWarehouse->id, $product->id, now()->subDays(2));
        $this->createTransfer($warehouse->id, $product->id, now()->subDays(30));

        $filename = sprintf(
            'stock_movement_warehouse_%d_%s_%s.csv',
            $warehouse->id,
            now()->subDays(7)->format('Y_m_d'),
            now()->format('Y_m_d')
        );

        Livewire::test(StockMovementBreakdownTable::class, [
            'entityType' => 'warehouse',
            'entityId' => $warehouse->id,
        ])
            ->assertSee('Export Test Product')
            ->assertDontSee('#'.$excludedTransfer->id)
            ->call('exportCsv')
            ->assertFileDownloaded($filename);
    }

    public function test_unprivileged_role_is_rejected_from_movement_tables(): void
    {
        $sales = User::factory()->sales()->create();
        $warehouse = Warehouse::factory()->create();
        $this->actingAs($sales);

        Livewire::test(StockEntityBreakdownTable::class)
            ->assertForbidden();

        Livewire::test(StockMovementBreakdownTable::class, [
            'entityType' => 'warehouse',
            'entityId' => $warehouse->id,
        ])->assertForbidden();
    }

    private function createTransfer(int $warehouseId, int $productTypeId, mixed $receivedAt): StockTransfer
    {
        $transfer = StockTransfer::factory()->create([
            'from_warehouse_id' => Warehouse::factory()->create()->id,
            'to_warehouse_id' => $warehouseId,
            'to_agent_id' => null,
            'status' => 'dispatched',
            'received_at' => $receivedAt,
        ]);

        StockTransferItem::factory()->create([
            'stock_transfer_id' => $transfer->id,
            'product_type_id' => $productTypeId,
            'grammage' => 100,
            'quantity' => 10,
        ]);

        return $transfer;
    }
}
