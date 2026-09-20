<?php

namespace Tests\Feature\Filament;

use App\Enums\AssignmentStatus;
use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\Pages\ManageOrders;
use App\Filament\Resources\SalesRecords\Pages\ListSalesRecords;
use App\Filament\Widgets\AccountantCreditSalesWidget;
use App\Filament\Widgets\CsrAssignedOrdersWidget;
use App\Filament\Widgets\CsrSalesRecordsWidget;
use App\Filament\Widgets\SalesPendingOrdersWidget;
use App\Models\Order;
use App\Models\SalesRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ProofReplacementTest extends TestCase
{
    use RefreshDatabase;

    public function test_rep_can_replace_order_payment_proof(): void
    {
        Storage::fake('s3');

        $rep = User::factory()->rep()->create();
        $order = Order::factory()->create(['user_id' => $rep->id]);
        Storage::disk('s3')->put('receipts/payment-proofs/old.png', 'old-content');
        $order->update([
            'payment_proof_path' => 'receipts/payment-proofs/old.png',
            'payment_proof_uploaded_by' => $rep->id,
            'payment_proof_uploaded_at' => now()->subDay(),
        ]);

        $this->actingAs($rep);

        Livewire::test(ManageOrders::class)
            ->mountTableAction('replacePaymentProof', $order->id)
            ->setTableActionData(['payment_proof_path' => UploadedFile::fake()->image('new-proof.png')])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $order->refresh();
        $this->assertStringStartsWith('receipts/payment-proofs/', $order->payment_proof_path);
        $this->assertNotSame('receipts/payment-proofs/old.png', $order->payment_proof_path);
        $this->assertSame($rep->id, $order->payment_proof_uploaded_by);
        Storage::disk('s3')->assertMissing('receipts/payment-proofs/old.png');
    }

    public function test_order_preview_offers_replace_only_to_eligible_roles(): void
    {
        $rep = User::factory()->rep()->create();
        $accountant = User::factory()->accountant()->create();
        $order = Order::factory()->create([
            'user_id' => $rep->id,
            'payment_proof_path' => 'receipts/payment-proofs/proof.png',
            'payment_proof_uploaded_by' => $rep->id,
        ]);

        $this->actingAs($rep);

        Livewire::test(ManageOrders::class)
            ->mountTableAction('viewPaymentProof', $order->id)
            ->assertMountedActionModalSee('Uploaded by')
            ->assertMountedActionModalSee('Replace Proof');

        $this->actingAs($accountant);

        Livewire::test(ManageOrders::class)
            ->mountTableAction('viewPaymentProof', $order->id)
            ->assertMountedActionModalSee('Uploaded by')
            ->assertMountedActionModalDontSee('Replace Proof');
    }

    public function test_csr_can_replace_proof_on_accepted_order(): void
    {
        Storage::fake('s3');

        $csr = User::factory()->communitySalesRepresentative()->create();
        $order = Order::factory()->create([
            'assigned_to' => $csr->id,
            'assignment_status' => AssignmentStatus::Accepted,
            'payment_proof_path' => 'receipts/payment-proofs/old.png',
            'payment_proof_uploaded_by' => $csr->id,
        ]);
        Storage::disk('s3')->put('receipts/payment-proofs/old.png', 'old-content');

        $this->actingAs($csr);

        Livewire::test(CsrAssignedOrdersWidget::class)
            ->mountTableAction('replacePaymentProof', $order->id)
            ->setTableActionData(['payment_proof_path' => UploadedFile::fake()->image('csr-new.png')])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertNotSame('receipts/payment-proofs/old.png', $order->fresh()->payment_proof_path);
        Storage::disk('s3')->assertMissing('receipts/payment-proofs/old.png');
    }

    public function test_sales_can_replace_proof_on_pending_order(): void
    {
        Storage::fake('s3');

        $sales = User::factory()->sales()->create();
        $order = Order::factory()->create([
            'user_id' => $sales->id,
            'status' => OrderStatus::Pending,
            'payment_proof_path' => 'receipts/payment-proofs/old.png',
            'payment_proof_uploaded_by' => $sales->id,
        ]);
        Storage::disk('s3')->put('receipts/payment-proofs/old.png', 'old-content');

        $this->actingAs($sales);

        Livewire::test(SalesPendingOrdersWidget::class)
            ->mountTableAction('replacePaymentProof', $order->id)
            ->setTableActionData(['payment_proof_path' => UploadedFile::fake()->image('sales-new.png')])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertNotSame('receipts/payment-proofs/old.png', $order->fresh()->payment_proof_path);
        Storage::disk('s3')->assertMissing('receipts/payment-proofs/old.png');
    }

    public function test_agent_can_replace_own_sales_receipt(): void
    {
        Storage::fake('s3');

        $agent = User::factory()->communitySalesRepresentative()->create();
        $record = SalesRecord::factory()->create([
            'agent_id' => $agent->id,
            'is_credit' => false,
            'status' => 'pending',
            'receipt_path' => 'receipts/sales-records/old.png',
            'receipt_original_name' => 'old.png',
        ]);
        Storage::disk('s3')->put('receipts/sales-records/old.png', 'old-content');

        $this->actingAs($agent);

        Livewire::test(ListSalesRecords::class)
            ->mountTableAction('replaceReceipt', $record->id)
            ->setTableActionData(['receipt_path' => UploadedFile::fake()->image('corrected.png')])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $record->refresh();
        $this->assertStringStartsWith('receipts/sales-records/', $record->receipt_path);
        $this->assertSame('corrected.png', $record->receipt_original_name);
        Storage::disk('s3')->assertMissing('receipts/sales-records/old.png');
    }

    public function test_agent_cannot_replace_receipt_on_locked_record(): void
    {
        $agent = User::factory()->communitySalesRepresentative()->create();
        $record = SalesRecord::factory()->approved()->create([
            'agent_id' => $agent->id,
            'is_credit' => false,
            'receipt_path' => 'receipts/sales-records/old.png',
        ]);

        $this->actingAs($agent);

        Livewire::test(ListSalesRecords::class)
            ->assertTableActionHidden('replaceReceipt', $record->id);
    }

    public function test_csr_can_replace_receipt_from_dashboard_widget(): void
    {
        Storage::fake('s3');

        $csr = User::factory()->communitySalesRepresentative()->create();
        $record = SalesRecord::factory()->create([
            'agent_id' => $csr->id,
            'is_credit' => false,
            'status' => 'pending',
            'receipt_path' => 'receipts/sales-records/old.png',
        ]);
        Storage::disk('s3')->put('receipts/sales-records/old.png', 'old-content');

        $this->actingAs($csr);

        Livewire::test(CsrSalesRecordsWidget::class)
            ->mountTableAction('replaceReceipt', $record->id)
            ->setTableActionData(['receipt_path' => UploadedFile::fake()->image('widget-new.png')])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertNotSame('receipts/sales-records/old.png', $record->fresh()->receipt_path);
        Storage::disk('s3')->assertMissing('receipts/sales-records/old.png');
    }

    public function test_accountant_can_replace_credit_sale_payment_proof(): void
    {
        Storage::fake('s3');

        $accountant = User::factory()->accountant()->create();
        $agent = User::factory()->communitySalesRepresentative()->create();
        $record = SalesRecord::factory()->credit()->create([
            'agent_id' => $agent->id,
            'payment_proof_path' => 'receipts/payment-proofs/old.png',
            'payment_proof_uploaded_by' => $agent->id,
        ]);
        Storage::disk('s3')->put('receipts/payment-proofs/old.png', 'old-content');

        $this->actingAs($accountant);

        Livewire::test(AccountantCreditSalesWidget::class)
            ->mountTableAction('replacePaymentProof', $record->id)
            ->setTableActionData(['payment_proof_path' => UploadedFile::fake()->image('acct-new.png')])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $record->refresh();
        $this->assertNotSame('receipts/payment-proofs/old.png', $record->payment_proof_path);
        $this->assertSame($accountant->id, $record->payment_proof_uploaded_by);
        Storage::disk('s3')->assertMissing('receipts/payment-proofs/old.png');
    }
}
