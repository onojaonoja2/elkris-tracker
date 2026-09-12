<?php

namespace Tests\Feature\Dashboards;

use App\Filament\Pages\WarehouseManagerDashboard;
use App\Livewire\CsrSalesValueTable;
use App\Livewire\StockMovementBreakdownTable;
use App\Models\SalesRecord;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WarehouseManagerDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_warehouse_manager_can_open_csr_sales_value_modal(): void
    {
        $manager = User::factory()->warehouseManager()->create();
        $this->actingAs($manager);

        Livewire::test(WarehouseManagerDashboard::class)
            ->call('mountAction', 'csrSalesValue')
            ->assertActionMounted('csrSalesValue');
    }

    public function test_csr_sales_value_table_aggregates_sales_and_revenue(): void
    {
        $manager = User::factory()->warehouseManager()->create();
        $csr = User::factory()->communitySalesRepresentative()->create(['name' => 'Mina CSR']);
        $this->actingAs($manager);

        SalesRecord::factory()->approved()->create(['agent_id' => $csr->id, 'total_value' => 1000]);
        SalesRecord::factory()->approved()->create(['agent_id' => $csr->id, 'total_value' => 2500]);
        SalesRecord::factory()->create(['agent_id' => $csr->id, 'status' => 'pending', 'total_value' => 700]);

        Livewire::test(CsrSalesValueTable::class)
            ->assertSee('Mina CSR')
            ->assertSee('₦4,200.00')
            ->assertSee('₦3,500.00');
    }

    public function test_csr_sales_value_table_drills_down_to_sales_records(): void
    {
        $manager = User::factory()->warehouseManager()->create();
        $csr = User::factory()->communitySalesRepresentative()->create(['name' => 'Mina CSR']);
        $this->actingAs($manager);

        SalesRecord::factory()->approved()->create([
            'agent_id' => $csr->id,
            'total_value' => 1000,
            'customer_name' => 'Kelechi Buyer',
        ]);

        Livewire::test(CsrSalesValueTable::class)
            ->call('selectCsr', $csr->id)
            ->assertSee('Kelechi Buyer')
            ->assertSee('Mina CSR');
    }

    public function test_warehouse_manager_can_open_stock_movement_breakdown_for_warehouse(): void
    {
        $manager = User::factory()->warehouseManager()->create();
        $warehouse = Warehouse::factory()->create(['name' => 'Remote Warehouse']);
        $this->actingAs($manager);

        Livewire::test(WarehouseManagerDashboard::class)
            ->call('openStockMovementBreakdown', 'warehouse', $warehouse->id, 'Ora herbal mix', 100)
            ->assertSet('breakdownEntityType', 'warehouse')
            ->assertSet('breakdownEntityId', $warehouse->id)
            ->assertSet('breakdownProduct', 'Ora herbal mix')
            ->assertSet('breakdownGrammage', 100)
            ->assertActionMounted('stockMovementBreakdown');
    }

    public function test_stock_movement_breakdown_table_allows_warehouse_manager(): void
    {
        $manager = User::factory()->warehouseManager()->create();
        $warehouse = Warehouse::factory()->create(['name' => 'Remote Warehouse']);
        $this->actingAs($manager);

        Livewire::test(StockMovementBreakdownTable::class, [
            'entityType' => 'warehouse',
            'entityId' => $warehouse->id,
        ])->assertOk();
    }
}
