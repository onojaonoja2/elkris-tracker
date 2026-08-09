<?php

namespace Tests\Feature\Widgets;

use App\Filament\Widgets\WarehouseDamagedReturnsWidget;
use App\Filament\Widgets\WarehouseDamagedStockTable;
use App\Models\DamagedInventory;
use App\Models\DamagedStockReturn;
use App\Models\ProductType;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WarehouseDamagedStockTest extends TestCase
{
    use RefreshDatabase;

    private function warehouseManager(): User
    {
        return User::factory()->warehouseManager()->create();
    }

    private function managedWarehouse(User $manager): Warehouse
    {
        return Warehouse::factory()->create(['manager_id' => $manager->id]);
    }

    private function damagedInventory(int $warehouseId, array $attributes = []): DamagedInventory
    {
        $productType = $attributes['product_type_id'] ?? ProductType::factory()->create();
        $return = DamagedStockReturn::factory()->create([
            'warehouse_id' => $warehouseId,
            'product_type_id' => $productType instanceof ProductType ? $productType->id : $productType,
        ]);

        return DamagedInventory::factory()->create(array_merge([
            'damaged_stock_return_id' => $return->id,
            'warehouse_id' => $warehouseId,
            'product_type_id' => $productType instanceof ProductType ? $productType->id : $productType,
        ], $attributes));
    }

    public function test_receiving_damaged_stock_return_records_damaged_inventory(): void
    {
        $manager = $this->warehouseManager();
        $warehouse = $this->managedWarehouse($manager);

        $return = DamagedStockReturn::factory()->approved()->create([
            'warehouse_id' => $warehouse->id,
            'quantity' => 12,
            'return_to_warehouse_initiated_by' => $manager->id,
            'return_to_warehouse_initiated_at' => now(),
        ]);

        $this->actingAs($manager);

        Livewire::test(WarehouseDamagedReturnsWidget::class)
            ->callTableAction('receiveReturn', $return->id, ['notes' => 'received at warehouse']);

        $this->assertDatabaseHas('damaged_inventory', [
            'damaged_stock_return_id' => $return->id,
            'warehouse_id' => $warehouse->id,
            'product_type_id' => $return->product_type_id,
            'grammage' => $return->grammage,
            'quantity' => 12,
            'status' => 'in_stock',
        ]);
    }

    public function test_send_damaged_stock_to_another_warehouse(): void
    {
        $manager = $this->warehouseManager();
        $warehouse = $this->managedWarehouse($manager);
        $destination = Warehouse::factory()->create();

        $inventory = $this->damagedInventory($warehouse->id, ['quantity' => 5]);

        $this->actingAs($manager);

        Livewire::test(WarehouseDamagedStockTable::class)
            ->callTableAction('sendToWarehouse', $inventory->id, [
                'destination_warehouse_id' => $destination->id,
            ]);

        $this->assertDatabaseHas('damaged_inventory', [
            'id' => $inventory->id,
            'status' => 'dispatched',
            'destination_warehouse_id' => $destination->id,
            'dispatched_by' => $manager->id,
        ]);

        $this->assertNotNull(DamagedInventory::find($inventory->id)->dispatched_at);
    }

    public function test_destroy_damaged_stock_requires_reason(): void
    {
        $manager = $this->warehouseManager();
        $warehouse = $this->managedWarehouse($manager);

        $inventory = $this->damagedInventory($warehouse->id);

        $this->actingAs($manager);

        Livewire::test(WarehouseDamagedStockTable::class)
            ->callTableAction('destroy', $inventory->id, ['destroy_reason' => '']);

        $this->assertDatabaseHas('damaged_inventory', [
            'id' => $inventory->id,
            'status' => 'in_stock',
        ]);
    }

    public function test_destroy_damaged_stock_records_disposition(): void
    {
        $manager = $this->warehouseManager();
        $warehouse = $this->managedWarehouse($manager);

        $inventory = $this->damagedInventory($warehouse->id);

        $this->actingAs($manager);

        Livewire::test(WarehouseDamagedStockTable::class)
            ->callTableAction('destroy', $inventory->id, ['destroy_reason' => 'Beyond repair']);

        $this->assertDatabaseHas('damaged_inventory', [
            'id' => $inventory->id,
            'status' => 'destroyed',
            'destroyed_by' => $manager->id,
            'destroy_reason' => 'Beyond repair',
        ]);

        $this->assertNotNull(DamagedInventory::find($inventory->id)->destroyed_at);
    }

    public function test_destination_warehouse_confirms_receipt_of_dispatched_damaged_stock(): void
    {
        $manager = $this->warehouseManager();
        $sourceWarehouse = $this->managedWarehouse($manager);
        $destinationManager = $this->warehouseManager();
        $destinationWarehouse = Warehouse::factory()->create(['manager_id' => $destinationManager->id]);

        $inventory = $this->damagedInventory($sourceWarehouse->id, [
            'status' => 'dispatched',
            'destination_warehouse_id' => $destinationWarehouse->id,
            'dispatched_by' => $manager->id,
            'dispatched_at' => now(),
        ]);

        $this->actingAs($destinationManager);

        Livewire::test(WarehouseDamagedStockTable::class)
            ->callTableAction('markAsReceived', $inventory->id);

        $this->assertDatabaseHas('damaged_inventory', [
            'id' => $inventory->id,
            'status' => 'in_stock',
            'warehouse_id' => $destinationWarehouse->id,
            'destination_warehouse_id' => null,
            'received_by' => $destinationManager->id,
        ]);

        $this->assertNotNull(DamagedInventory::find($inventory->id)->received_at);
    }

    public function test_manager_only_sees_damaged_stock_for_own_warehouses(): void
    {
        $manager = $this->warehouseManager();
        $warehouse = $this->managedWarehouse($manager);
        $otherWarehouse = Warehouse::factory()->create();

        $productType = ProductType::factory()->create();

        $own = $this->damagedInventory($warehouse->id, ['product_type_id' => $productType->id]);
        $other = $this->damagedInventory($otherWarehouse->id, ['product_type_id' => $productType->id]);

        $this->actingAs($manager);

        Livewire::test(WarehouseDamagedStockTable::class)
            ->assertCanSeeTableRecords([$own])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_manager_sees_damaged_stock_dispatched_to_their_warehouse(): void
    {
        $manager = $this->warehouseManager();
        $warehouse = $this->managedWarehouse($manager);
        $sourceWarehouse = Warehouse::factory()->create();

        $incoming = $this->damagedInventory($sourceWarehouse->id, [
            'status' => 'dispatched',
            'destination_warehouse_id' => $warehouse->id,
        ]);

        $this->actingAs($manager);

        Livewire::test(WarehouseDamagedStockTable::class)
            ->assertCanSeeTableRecords([$incoming]);
    }
}
