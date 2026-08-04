<?php

namespace Tests\Feature\Filament;

use App\Enums\StockTransferStatus;
use App\Filament\Resources\StockTransfers\StockTransferResource;
use App\Filament\Widgets\CsrPendingDispatchesWidget;
use App\Models\AgentStock;
use App\Models\ProductType;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AgentPendingDispatchesWidgetTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private Warehouse $warehouse;

    private User $manager;

    private ProductType $productType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->state(['role' => 'open_market'])->create();
        $this->manager = User::factory()->warehouseManager()->create();
        $this->warehouse = Warehouse::factory()->create(['manager_id' => $this->manager->id]);
        $this->productType = ProductType::factory()->create(['name' => 'Ora herbal mix', 'available_grammages' => [100]]);

        $this->actingAs($this->agent);
    }

    private function dispatchedTransfer(int $toAgentId, int $quantity = 10): StockTransfer
    {
        $transfer = StockTransfer::create([
            'from_warehouse_id' => $this->warehouse->id,
            'to_agent_id' => $toAgentId,
            'dispatched_by' => $this->manager->id,
            'requested_by' => $this->manager->id,
            'status' => StockTransferStatus::Dispatched,
        ]);

        StockTransferItem::create([
            'stock_transfer_id' => $transfer->id,
            'product_type_id' => $this->productType->id,
            'grammage' => 100,
            'quantity' => $quantity,
            'rejected_quantity' => 0,
        ]);

        return $transfer;
    }

    public function test_pending_dispatch_shown_only_for_own_agent(): void
    {
        $mine = $this->dispatchedTransfer($this->agent->id);
        $otherAgent = User::factory()->state(['role' => 'retail_market'])->create();
        $theirs = $this->dispatchedTransfer($otherAgent->id);

        Livewire::test(CsrPendingDispatchesWidget::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs]);
    }

    public function test_only_pending_statuses_are_listed(): void
    {
        $dispatched = $this->dispatchedTransfer($this->agent->id);
        $requested = StockTransfer::create([
            'from_warehouse_id' => $this->warehouse->id,
            'to_agent_id' => $this->agent->id,
            'status' => StockTransferStatus::Requested,
        ]);
        $received = $this->dispatchedTransfer($this->agent->id, 5);
        $received->update(['status' => StockTransferStatus::Received, 'received_at' => now()]);
        $cancelled = $this->dispatchedTransfer($this->agent->id, 3);
        $cancelled->update(['status' => StockTransferStatus::Cancelled]);

        Livewire::test(CsrPendingDispatchesWidget::class)
            ->assertCanSeeTableRecords([$dispatched, $requested, $received])
            ->assertCanNotSeeTableRecords([$cancelled]);
    }

    public function test_accept_receive_updates_stock_and_status(): void
    {
        $transfer = $this->dispatchedTransfer($this->agent->id, 20);
        $itemId = $transfer->items->first()->id;

        Livewire::test(CsrPendingDispatchesWidget::class)
            ->mountTableAction('acceptReceive', $transfer)
            ->set('mountedActions.0.data.items', [[
                'item_id' => $itemId,
                'accepted_quantity' => 20,
                'rejected_quantity' => 0,
                'rejection_reason' => null,
            ]])
            ->callMountedTableAction()
            ->assertHasNoActionErrors()
            ->assertNotified();

        $transfer->refresh();
        $this->assertEquals(StockTransferStatus::Received, $transfer->status);
        $this->assertEquals($this->agent->id, $transfer->received_by);

        $stock = AgentStock::where('user_id', $this->agent->id)
            ->where('product_name', 'Ora herbal mix')
            ->where('grammage', 100)
            ->first();

        $this->assertNotNull($stock);
        $this->assertEquals(20, $stock->quantity);
    }

    public function test_accept_receive_with_rejections(): void
    {
        $transfer = $this->dispatchedTransfer($this->agent->id, 10);
        $itemId = $transfer->items->first()->id;

        Livewire::test(CsrPendingDispatchesWidget::class)
            ->mountTableAction('acceptReceive', $transfer)
            ->set('mountedActions.0.data.items', [[
                'item_id' => $itemId,
                'accepted_quantity' => 7,
                'rejected_quantity' => 3,
                'rejection_reason' => 'Damaged packaging',
            ]])
            ->callMountedTableAction()
            ->assertHasNoActionErrors()
            ->assertNotified();

        $item = $transfer->items->first()->refresh();
        $this->assertEquals(3, $item->rejected_quantity);
        $this->assertEquals('Damaged packaging', $item->rejection_reason);

        $stock = AgentStock::where('user_id', $this->agent->id)
            ->where('product_name', 'Ora herbal mix')
            ->where('grammage', 100)
            ->first();

        $this->assertEquals(7, $stock->quantity);
    }

    public function test_view_received_breakdown_is_available_for_received_transfers(): void
    {
        $transfer = $this->dispatchedTransfer($this->agent->id, 10);
        StockTransferService::receive($transfer, [
            [
                'item_id' => $transfer->items->first()->id,
                'accepted_quantity' => 8,
                'rejected_quantity' => 2,
                'rejection_reason' => 'Broken',
            ],
        ]);

        Livewire::test(CsrPendingDispatchesWidget::class)
            ->assertTableActionExists('viewReceived', record: $transfer)
            ->assertTableActionVisible('viewReceived', record: $transfer);

        $this->assertStringContainsString(
            'Broken',
            view('filament.stock-received-breakdown', ['transfer' => $transfer])->render()
        );
    }

    public function test_widget_forbidden_for_non_agent_roles(): void
    {
        $accountant = User::factory()->accountant()->create();
        $this->actingAs($accountant);

        $this->assertFalse(CsrPendingDispatchesWidget::canView());
    }

    public function test_stock_transfer_resource_scope_includes_dispatches_addressed_to_agent(): void
    {
        $dispatch = $this->dispatchedTransfer($this->agent->id);

        $query = StockTransferResource::getEloquentQuery();

        $this->assertTrue($query->whereKey($dispatch->id)->exists());
    }
}
