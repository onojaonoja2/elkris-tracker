<?php

namespace Tests\Feature\Services;

use App\Models\SalesRecord;
use App\Models\User;
use App\Services\SalesRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SalesRecordReplacementTest extends TestCase
{
    use RefreshDatabase;

    public function test_replace_payment_proof_swaps_file_and_clears_review_markers(): void
    {
        Storage::fake('s3');

        $agent = User::factory()->communitySalesRepresentative()->create();
        $record = SalesRecord::factory()->credit()->create([
            'agent_id' => $agent->id,
            'payment_proof_path' => 'receipts/payment-proofs/old.png',
            'payment_proof_uploaded_by' => $agent->id,
            'proof_review_requested_at' => now()->subHour(),
            'proof_review_requested_by' => $agent->id,
        ]);
        Storage::disk('s3')->put('receipts/payment-proofs/old.png', 'old-content');

        SalesRecordService::replacePaymentProof($record, [
            'payment_proof_path' => 'receipts/payment-proofs/new.png',
        ], $agent->id);

        $record->refresh();
        $this->assertSame('receipts/payment-proofs/new.png', $record->payment_proof_path);
        $this->assertSame($agent->id, $record->payment_proof_uploaded_by);
        $this->assertNull($record->proof_review_requested_at);
        $this->assertNull($record->proof_review_requested_by);
        Storage::disk('s3')->assertMissing('receipts/payment-proofs/old.png');
    }

    public function test_replace_payment_proof_requires_existing_proof(): void
    {
        $agent = User::factory()->communitySalesRepresentative()->create();
        $record = SalesRecord::factory()->credit()->create(['agent_id' => $agent->id]);

        $this->expectException(ValidationException::class);

        SalesRecordService::replacePaymentProof($record, [
            'payment_proof_path' => 'receipts/payment-proofs/new.png',
        ], $agent->id);
    }

    public function test_replace_payment_proof_rejected_once_collected(): void
    {
        $agent = User::factory()->communitySalesRepresentative()->create();
        $record = SalesRecord::factory()->collected()->create([
            'agent_id' => $agent->id,
            'payment_proof_path' => 'receipts/payment-proofs/old.png',
        ]);

        $this->expectException(ValidationException::class);

        SalesRecordService::replacePaymentProof($record, [
            'payment_proof_path' => 'receipts/payment-proofs/new.png',
        ], $agent->id);
    }

    public function test_replace_receipt_swaps_file_and_updates_original_name(): void
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

        SalesRecordService::replaceReceipt($record, [
            'receipt_path' => 'receipts/sales-records/new.png',
            'receipt_original_name' => 'new-receipt.png',
        ], $agent->id);

        $record->refresh();
        $this->assertSame('receipts/sales-records/new.png', $record->receipt_path);
        $this->assertSame('new-receipt.png', $record->receipt_original_name);
        Storage::disk('s3')->assertMissing('receipts/sales-records/old.png');
    }

    public function test_replace_receipt_rejected_for_locked_records(): void
    {
        $agent = User::factory()->communitySalesRepresentative()->create();
        $record = SalesRecord::factory()->approved()->create([
            'agent_id' => $agent->id,
            'receipt_path' => 'receipts/sales-records/old.png',
        ]);

        $this->expectException(ValidationException::class);

        SalesRecordService::replaceReceipt($record, [
            'receipt_path' => 'receipts/sales-records/new.png',
        ], $agent->id);
    }

    public function test_replace_receipt_rejected_for_non_owner(): void
    {
        $agent = User::factory()->communitySalesRepresentative()->create();
        $other = User::factory()->communitySalesRepresentative()->create();
        $record = SalesRecord::factory()->create([
            'agent_id' => $agent->id,
            'is_credit' => false,
            'status' => 'pending',
            'receipt_path' => 'receipts/sales-records/old.png',
        ]);

        $this->expectException(ValidationException::class);

        SalesRecordService::replaceReceipt($record, [
            'receipt_path' => 'receipts/sales-records/new.png',
        ], $other->id);
    }

    public function test_replace_receipt_allowed_for_admin_on_foreign_record(): void
    {
        $admin = User::factory()->admin()->create();
        $agent = User::factory()->communitySalesRepresentative()->create();
        $record = SalesRecord::factory()->create([
            'agent_id' => $agent->id,
            'is_credit' => false,
            'status' => 'pending',
            'receipt_path' => 'receipts/sales-records/old.png',
        ]);

        SalesRecordService::replaceReceipt($record, [
            'receipt_path' => 'receipts/sales-records/new.png',
        ], $admin->id);

        $this->assertSame('receipts/sales-records/new.png', $record->fresh()->receipt_path);
    }
}
