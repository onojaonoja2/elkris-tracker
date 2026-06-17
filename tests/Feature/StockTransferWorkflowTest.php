<?php

namespace Tests\Feature;

use App\Enums\StockTransferStatus;
use App\Models\ProductType;
use App\Models\Stockist;
use App\Models\StockistStock;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockTransferWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_transfer_can_be_created(): void
    {
        $warehouse = Warehouse::factory()->create();
        $stockist = Stockist::factory()->create();
        $requester = User::factory()->fieldAgent()->create();

        $transfer = StockTransfer::create([
            'from_warehouse_id' => $warehouse->id,
            'to_stockist_id' => $stockist->id,
            'requested_by' => $requester->id,
            'status' => StockTransferStatus::Requested,
            'notes' => 'Urgent stock request',
        ]);

        $this->assertDatabaseHas('stock_transfers', [
            'id' => $transfer->id,
            'from_warehouse_id' => $warehouse->id,
            'to_stockist_id' => $stockist->id,
            'status' => StockTransferStatus::Requested,
        ]);
    }

    public function test_supervisor_can_approve_stock_transfer(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $warehouse = Warehouse::factory()->create();
        $stockist = Stockist::factory()->create();
        $requester = User::factory()->fieldAgent()->create();

        $transfer = StockTransfer::create([
            'from_warehouse_id' => $warehouse->id,
            'to_stockist_id' => $stockist->id,
            'requested_by' => $requester->id,
            'status' => StockTransferStatus::Requested,
        ]);

        $this->actingAs($supervisor);

        StockTransferService::approve($transfer);

        $transfer->refresh();

        $this->assertEquals(StockTransferStatus::Approved, $transfer->status);
        $this->assertEquals($supervisor->id, $transfer->approved_by);
        $this->assertNotNull($transfer->approved_at);
    }

    public function test_stock_transfer_can_be_dispatched(): void
    {
        $warehouse = Warehouse::factory()->create();
        $stockist = Stockist::factory()->create();
        $requester = User::factory()->fieldAgent()->create();

        $transfer = StockTransfer::create([
            'from_warehouse_id' => $warehouse->id,
            'to_stockist_id' => $stockist->id,
            'requested_by' => $requester->id,
            'status' => StockTransferStatus::Approved,
        ]);

        $transfer->update([
            'status' => StockTransferStatus::Dispatched,
            'dispatched_by' => $requester->id,
        ]);

        $transfer->refresh();

        $this->assertEquals(StockTransferStatus::Dispatched, $transfer->status);
    }

    public function test_stock_transfer_receive_updates_inventory(): void
    {
        $warehouse = Warehouse::factory()->create();
        $stockist = Stockist::factory()->create();
        $requester = User::factory()->fieldAgent()->create();
        $receiver = User::factory()->stockist()->create();
        $productType = ProductType::factory()->create(['name' => 'Ora herbal mix']);

        $transfer = StockTransfer::create([
            'from_warehouse_id' => $warehouse->id,
            'to_stockist_id' => $stockist->id,
            'requested_by' => $requester->id,
            'status' => StockTransferStatus::Dispatched,
        ]);

        $item = StockTransferItem::create([
            'stock_transfer_id' => $transfer->id,
            'product_type_id' => $productType->id,
            'grammage' => 100,
            'quantity' => 20,
            'rejected_quantity' => 0,
        ]);

        $this->actingAs($receiver);

        StockTransferService::receive($transfer, [
            [
                'item_id' => $item->id,
                'accepted_quantity' => 20,
                'rejected_quantity' => 0,
            ],
        ]);

        $stock = StockistStock::where('stockist_id', $stockist->id)
            ->where('product_name', 'Ora herbal mix')
            ->where('grammage', 100)
            ->first();

        $this->assertNotNull($stock);
        $this->assertEquals(20, $stock->quantity);

        $transfer->refresh();
        $this->assertEquals(StockTransferStatus::Received, $transfer->status);
    }

    public function test_stock_transfer_rejection_records_reason(): void
    {
        $warehouse = Warehouse::factory()->create();
        $stockist = Stockist::factory()->create();
        $requester = User::factory()->fieldAgent()->create();
        $approver = User::factory()->supervisor()->create();

        $transfer = StockTransfer::create([
            'from_warehouse_id' => $warehouse->id,
            'to_stockist_id' => $stockist->id,
            'requested_by' => $requester->id,
            'status' => StockTransferStatus::Requested,
        ]);

        $this->actingAs($approver);

        StockTransferService::reject($transfer, 'Insufficient warehouse inventory');

        $transfer->refresh();

        $this->assertEquals(StockTransferStatus::Cancelled, $transfer->status);
        $this->assertEquals('Insufficient warehouse inventory', $transfer->rejection_reason);
    }
}
