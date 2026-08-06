<?php

namespace Tests\Feature\Widgets;

use App\Enums\StockTransferStatus;
use App\Filament\Widgets\AccountantDamagedReturnsWidget;
use App\Filament\Widgets\AccountantSalesRecordsWidget;
use App\Filament\Widgets\AccountantStockTransferApprovalWidget;
use App\Filament\Widgets\SupervisorDamagedReturnsWidget;
use App\Filament\Widgets\SupervisorSalesRecordsWidget;
use App\Filament\Widgets\SupervisorStockTransferApprovalWidget;
use App\Models\DamagedStockReturn;
use App\Models\ProductType;
use App\Models\SalesRecord;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ApprovalRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_transfer_widget_sees_only_csr_requests(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $this->actingAs($supervisor);

        $warehouse = Warehouse::factory()->create();
        $agent = User::factory()->communitySalesRepresentative()->create();

        $csr = $this->createTransfer('community_sales_representative', $warehouse, $agent);
        $openMarket = $this->createTransfer('open_market', $warehouse, $agent);

        Livewire::test(SupervisorStockTransferApprovalWidget::class)
            ->assertCanSeeTableRecords([$csr])
            ->assertCanNotSeeTableRecords([$openMarket]);
    }

    public function test_accountant_transfer_widget_sees_only_open_retail_requests(): void
    {
        $accountant = User::factory()->accountant()->create();
        $this->actingAs($accountant);

        $warehouse = Warehouse::factory()->create();
        $agent = User::factory()->communitySalesRepresentative()->create();

        $csr = $this->createTransfer('community_sales_representative', $warehouse, $agent);
        $openMarket = $this->createTransfer('open_market', $warehouse, $agent);

        Livewire::test(AccountantStockTransferApprovalWidget::class)
            ->assertCanNotSeeTableRecords([$csr])
            ->assertCanSeeTableRecords([$openMarket]);
    }

    public function test_accountant_can_approve_open_retail_transfer(): void
    {
        $accountant = User::factory()->accountant()->create();
        $this->actingAs($accountant);

        $warehouse = Warehouse::factory()->create();
        $agent = User::factory()->communitySalesRepresentative()->create();
        $transfer = $this->createTransfer('retail_market', $warehouse, $agent);

        Livewire::test(AccountantStockTransferApprovalWidget::class)
            ->callTableAction('accountantApprove', $transfer->id);

        $this->assertDatabaseHas('stock_transfers', [
            'id' => $transfer->id,
            'status' => StockTransferStatus::Approved,
            'approved_by' => $accountant->id,
        ]);
    }

    public function test_accountant_can_reject_open_retail_transfer(): void
    {
        $accountant = User::factory()->accountant()->create();
        $this->actingAs($accountant);

        $warehouse = Warehouse::factory()->create();
        $agent = User::factory()->communitySalesRepresentative()->create();
        $transfer = $this->createTransfer('open_market', $warehouse, $agent);

        Livewire::test(AccountantStockTransferApprovalWidget::class)
            ->callTableAction('accountantReject', $transfer->id, ['rejection_reason' => 'Not enough stock']);

        $this->assertDatabaseHas('stock_transfers', [
            'id' => $transfer->id,
            'status' => StockTransferStatus::Cancelled,
            'rejection_reason' => 'Not enough stock',
        ]);
    }

    public function test_supervisor_damaged_returns_widget_sees_only_csr_returns(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $this->actingAs($supervisor);

        $productType = ProductType::factory()->create(['name' => fake()->unique()->word()]);
        $csrReturn = DamagedStockReturn::factory()->create([
            'user_id' => User::factory()->communitySalesRepresentative()->create(['name' => 'CSR Returner'])->id,
            'product_type_id' => $productType->id,
            'status' => 'pending',
        ]);
        $openReturn = DamagedStockReturn::factory()->create([
            'user_id' => User::factory()->openMarket()->create(['name' => 'Open Returner'])->id,
            'product_type_id' => $productType->id,
            'status' => 'pending',
        ]);

        Livewire::test(SupervisorDamagedReturnsWidget::class)
            ->assertCanSeeTableRecords([$csrReturn])
            ->assertCanNotSeeTableRecords([$openReturn]);
    }

    public function test_accountant_damaged_returns_widget_sees_approved_csr_and_non_csr_returns(): void
    {
        $accountant = User::factory()->accountant()->create();
        $this->actingAs($accountant);

        $productType = ProductType::factory()->create(['name' => fake()->unique()->word()]);

        $approvedCsr = DamagedStockReturn::factory()->create([
            'user_id' => User::factory()->communitySalesRepresentative()->create()->id,
            'product_type_id' => $productType->id,
            'status' => 'pending',
            'supervisor_approved_by' => User::factory()->supervisor()->create()->id,
            'supervisor_approved_at' => now(),
        ]);
        $unapprovedCsr = DamagedStockReturn::factory()->create([
            'user_id' => User::factory()->communitySalesRepresentative()->create()->id,
            'product_type_id' => $productType->id,
            'status' => 'pending',
            'supervisor_approved_by' => null,
            'supervisor_approved_at' => null,
        ]);
        $openReturn = DamagedStockReturn::factory()->create([
            'user_id' => User::factory()->retailMarket()->create()->id,
            'product_type_id' => $productType->id,
            'status' => 'pending',
        ]);

        Livewire::test(AccountantDamagedReturnsWidget::class)
            ->assertCanSeeTableRecords([$approvedCsr, $openReturn])
            ->assertCanNotSeeTableRecords([$unapprovedCsr]);
    }

    public function test_accountant_sales_widget_includes_verified_csr_and_non_csr_but_not_unverified_csr(): void
    {
        $accountant = User::factory()->accountant()->create();
        $this->actingAs($accountant);

        $csr = User::factory()->communitySalesRepresentative()->create();
        $openMarket = User::factory()->openMarket()->create();

        $verifiedCsr = SalesRecord::factory()->create([
            'agent_id' => $csr->id,
            'agent_type' => 'community_sales_representative',
            'status' => 'pending',
            'supervisor_verified_at' => now(),
            'supervisor_verified_by' => User::factory()->supervisor()->create()->id,
        ]);
        $unverifiedCsr = SalesRecord::factory()->create([
            'agent_id' => $csr->id,
            'agent_type' => 'community_sales_representative',
            'status' => 'pending',
            'supervisor_verified_at' => null,
        ]);
        $openSale = SalesRecord::factory()->create([
            'agent_id' => $openMarket->id,
            'agent_type' => 'open_market',
            'status' => 'receipt_uploaded',
            'supervisor_verified_at' => null,
        ]);

        Livewire::test(AccountantSalesRecordsWidget::class)
            ->assertCanSeeTableRecords([$verifiedCsr, $openSale])
            ->assertCanNotSeeTableRecords([$unverifiedCsr]);
    }

    public function test_supervisor_sales_widget_approve_action_marks_record(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $this->actingAs($supervisor);

        $csr = User::factory()->communitySalesRepresentative()->create();
        $record = SalesRecord::factory()->create([
            'agent_id' => $csr->id,
            'agent_type' => 'community_sales_representative',
            'status' => 'pending',
            'supervisor_verified_at' => null,
        ]);

        Livewire::test(SupervisorSalesRecordsWidget::class)
            ->callTableAction('supervisorApprove', $record->id, ['supervisor_notes' => 'Verified on site']);

        $this->assertDatabaseHas('sales_records', [
            'id' => $record->id,
            'supervisor_verified_by' => $supervisor->id,
            'supervisor_notes' => 'Verified on site',
        ]);
    }

    public function test_transfer_widgets_are_role_gated(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $accountant = User::factory()->accountant()->create();

        $this->actingAs($supervisor);
        $this->assertTrue(SupervisorStockTransferApprovalWidget::canView());
        $this->assertFalse(AccountantStockTransferApprovalWidget::canView());

        $this->actingAs($accountant);
        $this->assertFalse(SupervisorStockTransferApprovalWidget::canView());
        $this->assertTrue(AccountantStockTransferApprovalWidget::canView());
    }

    private function createTransfer(string $requesterRole, Warehouse $warehouse, User $agent): StockTransfer
    {
        return StockTransfer::create([
            'from_warehouse_id' => $warehouse->id,
            'to_agent_id' => $agent->id,
            'requested_by' => User::factory()->create(['role' => $requesterRole])->id,
            'status' => StockTransferStatus::Requested,
            'requires_approval' => true,
        ]);
    }
}
