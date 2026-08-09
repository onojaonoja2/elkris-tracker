<?php

namespace Tests\Feature\StockTransfers;

use App\Enums\StockTransferStatus;
use App\Filament\Resources\StockTransfers\Pages\ListStockTransfers;
use App\Models\SalesRecord;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StockTransferResourceSalesLinkedTest extends TestCase
{
    use RefreshDatabase;

    private function makeSalesLinkedTransfer(array $attributes = []): StockTransfer
    {
        $salesRecord = SalesRecord::factory()->create();

        return StockTransfer::factory()->create(array_merge([
            'status' => StockTransferStatus::Requested,
            'sales_record_id' => $salesRecord->id,
        ], $attributes));
    }

    private function makeStandaloneTransfer(array $attributes = []): StockTransfer
    {
        return StockTransfer::factory()->create(array_merge([
            'from_warehouse_id' => Warehouse::factory(),
            'to_agent_id' => User::factory()->communitySalesRepresentative(),
            'status' => StockTransferStatus::Requested,
        ], $attributes));
    }

    public function test_accountant_approval_actions_hidden_for_sales_linked_request(): void
    {
        $accountant = User::factory()->accountant()->create();
        $salesLinked = $this->makeSalesLinkedTransfer(['requested_by' => $accountant->id]);
        $standalone = $this->makeStandaloneTransfer(['requested_by' => $accountant->id]);

        $this->actingAs($accountant);

        Livewire::test(ListStockTransfers::class)
            ->assertTableActionHidden('accountantApprove', $salesLinked->id)
            ->assertTableActionHidden('accountantReject', $salesLinked->id)
            ->assertTableActionHidden('cancel', $salesLinked->id)
            ->assertTableActionVisible('accountantApprove', $standalone->id)
            ->assertTableActionVisible('accountantReject', $standalone->id)
            ->assertTableActionVisible('cancel', $standalone->id);
    }

    public function test_supervisor_approval_action_hidden_for_sales_linked_request(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $salesLinked = $this->makeSalesLinkedTransfer(['requested_by' => $supervisor->id]);
        $standalone = $this->makeStandaloneTransfer(['requested_by' => $supervisor->id]);

        $this->actingAs($supervisor);

        Livewire::test(ListStockTransfers::class)
            ->assertTableActionHidden('supervisorApprove', $salesLinked->id)
            ->assertTableActionVisible('supervisorApprove', $standalone->id);
    }

    public function test_dispatch_and_cancel_hidden_for_sales_linked_approved_transfer(): void
    {
        $user = User::factory()->supervisor()->create();
        $salesLinked = $this->makeSalesLinkedTransfer([
            'status' => StockTransferStatus::Approved,
            'requested_by' => $user->id,
        ]);
        $standalone = $this->makeStandaloneTransfer([
            'status' => StockTransferStatus::Approved,
            'requested_by' => $user->id,
        ]);

        $this->actingAs($user);

        Livewire::test(ListStockTransfers::class)
            ->assertTableActionHidden('dispatch', $salesLinked->id)
            ->assertTableActionHidden('cancel', $salesLinked->id)
            ->assertTableActionVisible('dispatch', $standalone->id)
            ->assertTableActionVisible('cancel', $standalone->id);
    }

    public function test_sales_linked_transfer_is_excluded_from_bulk_delete(): void
    {
        $admin = User::factory()->admin()->create();
        $salesLinked = $this->makeSalesLinkedTransfer(['requested_by' => $admin->id]);
        $standalone = $this->makeStandaloneTransfer(['requested_by' => $admin->id]);

        $this->actingAs($admin);

        Livewire::test(ListStockTransfers::class)
            ->callTableBulkAction('delete', [$salesLinked->id, $standalone->id]);

        $this->assertDatabaseHas('stock_transfers', ['id' => $salesLinked->id]);
        $this->assertDatabaseMissing('stock_transfers', ['id' => $standalone->id]);
    }
}
