<?php

namespace Tests\Feature\Sales;

use App\Enums\StockTransferStatus;
use App\Models\Inventory;
use App\Models\ProductType;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\SalesRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WarehouseAllocationTest extends TestCase
{
    use RefreshDatabase;

    private ProductType $productType;

    private array $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productType = ProductType::factory()->create([
            'name' => 'Ora Herbal Mix',
            'available_grammages' => [['grammage' => 100, 'carton_quantity' => 20]],
        ]);

        $this->product = [
            'product_name' => $this->productType->name,
            'grammage' => 100,
            'quantity' => 5,
            'price' => 1000.00,
        ];
    }

    private function makeWarehouseWithStock(int $quantity): Warehouse
    {
        $warehouse = Warehouse::factory()->create();

        Inventory::factory()->create([
            'warehouse_id' => $warehouse->id,
            'product_type_id' => $this->productType->id,
            'grammage' => 100,
            'quantity' => $quantity,
        ]);

        return $warehouse;
    }

    public function test_open_market_submit_creates_pending_record_and_stock_request(): void
    {
        $agent = User::factory()->openMarket()->create();
        $warehouse = $this->makeWarehouseWithStock(20);

        $this->actingAs($agent);

        $record = SalesRecordService::submitSale([
            'products' => [$this->product],
            'total_value' => 5000.00,
            'is_credit' => false,
            'warehouse_id' => $warehouse->id,
        ]);

        $this->assertDatabaseHas('sales_records', [
            'id' => $record->id,
            'agent_id' => $agent->id,
            'agent_type' => 'open_market',
            'warehouse_id' => $warehouse->id,
            'status' => 'pending',
            'stock_deducted_at' => null,
        ]);

        $this->assertDatabaseMissing('agent_stocks', ['user_id' => $agent->id]);

        $this->assertDatabaseHas('stock_transfers', [
            'from_warehouse_id' => $warehouse->id,
            'to_agent_id' => $agent->id,
            'requested_by' => $agent->id,
            'sales_record_id' => $record->id,
            'status' => StockTransferStatus::Requested,
            'source_type' => 'sales_record',
        ]);

        $this->assertDatabaseHas('stock_transfer_items', [
            'product_type_id' => $this->productType->id,
            'grammage' => 100,
            'quantity' => 5,
        ]);

        $this->assertDatabaseHas('inventories', [
            'warehouse_id' => $warehouse->id,
            'product_type_id' => $this->productType->id,
            'grammage' => 100,
            'quantity' => 20,
        ]);
    }

    public function test_submit_blocks_when_warehouse_stock_is_insufficient(): void
    {
        $agent = User::factory()->openMarket()->create();
        $warehouse = $this->makeWarehouseWithStock(2);

        $this->actingAs($agent);

        try {
            SalesRecordService::submitSale([
                'products' => [$this->product],
                'total_value' => 5000.00,
                'is_credit' => false,
                'warehouse_id' => $warehouse->id,
            ]);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('products', $e->errors());
        }

        $this->assertDatabaseMissing('sales_records', ['agent_id' => $agent->id]);
        $this->assertDatabaseMissing('stock_transfers', ['from_warehouse_id' => $warehouse->id]);
    }

    public function test_submit_requires_warehouse_id(): void
    {
        $agent = User::factory()->retailMarket()->create();

        $this->actingAs($agent);

        try {
            SalesRecordService::submitSale([
                'products' => [$this->product],
                'total_value' => 5000.00,
                'is_credit' => false,
            ]);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('warehouse_id', $e->errors());
        }
    }

    public function test_approve_allocates_warehouse_stock_to_agent(): void
    {
        $agent = User::factory()->openMarket()->create();
        $warehouse = $this->makeWarehouseWithStock(20);
        $accountant = User::factory()->accountant()->create();

        $this->actingAs($agent);

        $record = SalesRecordService::submitSale([
            'products' => [$this->product],
            'total_value' => 5000.00,
            'is_credit' => false,
            'warehouse_id' => $warehouse->id,
        ]);

        SalesRecordService::approve($record, [], $accountant->id);

        $this->assertDatabaseHas('inventories', [
            'warehouse_id' => $warehouse->id,
            'product_type_id' => $this->productType->id,
            'grammage' => 100,
            'quantity' => 15,
        ]);

        $this->assertDatabaseHas('agent_stocks', [
            'user_id' => $agent->id,
            'product_type_id' => $this->productType->id,
            'product_name' => $this->productType->name,
            'grammage' => 100,
            'quantity' => 5,
        ]);

        $transfer = StockTransfer::where('sales_record_id', $record->id)->firstOrFail();

        $this->assertEquals(StockTransferStatus::Received, $transfer->status);
        $this->assertEquals($accountant->id, $transfer->approved_by);
        $this->assertNotNull($transfer->received_at);

        $record->refresh();
        $this->assertEquals('approved', $record->status);
        $this->assertNotNull($record->stock_deducted_at);
    }

    public function test_approve_fails_when_warehouse_stock_depleted_after_submit(): void
    {
        $agent = User::factory()->openMarket()->create();
        $warehouse = $this->makeWarehouseWithStock(20);
        $accountant = User::factory()->accountant()->create();

        $this->actingAs($agent);

        $record = SalesRecordService::submitSale([
            'products' => [$this->product],
            'total_value' => 5000.00,
            'is_credit' => false,
            'warehouse_id' => $warehouse->id,
        ]);

        Inventory::where('warehouse_id', $warehouse->id)
            ->where('product_type_id', $this->productType->id)
            ->update(['quantity' => 2]);

        try {
            SalesRecordService::approve($record, [], $accountant->id);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }

        $this->assertDatabaseHas('inventories', [
            'warehouse_id' => $warehouse->id,
            'product_type_id' => $this->productType->id,
            'grammage' => 100,
            'quantity' => 2,
        ]);

        $this->assertDatabaseMissing('agent_stocks', ['user_id' => $agent->id]);

        $transfer = StockTransfer::where('sales_record_id', $record->id)->firstOrFail();
        $this->assertEquals(StockTransferStatus::Requested, $transfer->status);

        $this->assertEquals('pending', $record->fresh()->status);
    }

    public function test_reject_cancels_stock_request_without_moving_stock(): void
    {
        $agent = User::factory()->retailMarket()->create();
        $warehouse = $this->makeWarehouseWithStock(20);
        $accountant = User::factory()->accountant()->create();

        $this->actingAs($agent);

        $record = SalesRecordService::submitSale([
            'products' => [$this->product],
            'total_value' => 5000.00,
            'is_credit' => false,
            'warehouse_id' => $warehouse->id,
        ]);

        SalesRecordService::reject($record, 'Invalid receipt', $accountant->id);

        $this->assertDatabaseHas('inventories', [
            'warehouse_id' => $warehouse->id,
            'product_type_id' => $this->productType->id,
            'grammage' => 100,
            'quantity' => 20,
        ]);

        $this->assertDatabaseMissing('agent_stocks', ['user_id' => $agent->id]);

        $transfer = StockTransfer::where('sales_record_id', $record->id)->firstOrFail();

        $this->assertEquals(StockTransferStatus::Cancelled, $transfer->status);
        $this->assertEquals('Invalid receipt', $transfer->rejection_reason);

        $this->assertEquals('rejected', $record->fresh()->status);
    }

    public function test_approve_recovers_when_stock_request_was_standalone_approved_without_movement(): void
    {
        $agent = User::factory()->openMarket()->create();
        $warehouse = $this->makeWarehouseWithStock(20);
        $accountant = User::factory()->accountant()->create();

        $this->actingAs($agent);

        $record = SalesRecordService::submitSale([
            'products' => [$this->product],
            'total_value' => 5000.00,
            'is_credit' => false,
            'warehouse_id' => $warehouse->id,
        ]);

        $transfer = StockTransfer::where('sales_record_id', $record->id)->firstOrFail();

        $transfer->update([
            'status' => StockTransferStatus::Approved,
            'approved_by' => $accountant->id,
            'approved_at' => now(),
        ]);

        SalesRecordService::approve($record, [], $accountant->id);

        $this->assertDatabaseHas('inventories', [
            'warehouse_id' => $warehouse->id,
            'product_type_id' => $this->productType->id,
            'grammage' => 100,
            'quantity' => 15,
        ]);

        $this->assertDatabaseHas('agent_stocks', [
            'user_id' => $agent->id,
            'product_type_id' => $this->productType->id,
            'product_name' => $this->productType->name,
            'grammage' => 100,
            'quantity' => 5,
        ]);

        $transfer->refresh();

        $this->assertEquals(StockTransferStatus::Received, $transfer->status);
        $this->assertNotNull($transfer->received_at);

        $this->assertEquals('approved', $record->fresh()->status);
    }

    public function test_approve_fails_when_stock_request_was_dispatched_standalone(): void
    {
        $agent = User::factory()->openMarket()->create();
        $warehouse = $this->makeWarehouseWithStock(20);
        $accountant = User::factory()->accountant()->create();

        $this->actingAs($agent);

        $record = SalesRecordService::submitSale([
            'products' => [$this->product],
            'total_value' => 5000.00,
            'is_credit' => false,
            'warehouse_id' => $warehouse->id,
        ]);

        $transfer = StockTransfer::where('sales_record_id', $record->id)->firstOrFail();

        $transfer->update([
            'status' => StockTransferStatus::Dispatched,
            'dispatched_by' => $accountant->id,
        ]);

        try {
            SalesRecordService::approve($record, [], $accountant->id);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }

        $this->assertDatabaseHas('inventories', [
            'warehouse_id' => $warehouse->id,
            'product_type_id' => $this->productType->id,
            'grammage' => 100,
            'quantity' => 20,
        ]);

        $this->assertDatabaseMissing('agent_stocks', ['user_id' => $agent->id]);
    }

    public function test_approve_fails_when_stock_request_was_already_received(): void
    {
        $agent = User::factory()->openMarket()->create();
        $warehouse = $this->makeWarehouseWithStock(20);
        $accountant = User::factory()->accountant()->create();

        $this->actingAs($agent);

        $record = SalesRecordService::submitSale([
            'products' => [$this->product],
            'total_value' => 5000.00,
            'is_credit' => false,
            'warehouse_id' => $warehouse->id,
        ]);

        SalesRecordService::approve($record, [], $accountant->id);

        try {
            SalesRecordService::approve($record, [], $accountant->id);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }

        $this->assertDatabaseHas('inventories', [
            'warehouse_id' => $warehouse->id,
            'product_type_id' => $this->productType->id,
            'grammage' => 100,
            'quantity' => 15,
        ]);
    }
}
