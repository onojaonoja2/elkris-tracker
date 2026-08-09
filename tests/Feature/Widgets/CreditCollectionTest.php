<?php

namespace Tests\Feature\Widgets;

use App\Models\CreditCollection;
use App\Models\SalesRecord;
use App\Models\User;
use App\Services\SalesRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CreditCollectionTest extends TestCase
{
    use RefreshDatabase;

    private function createApprovedCredit(User $agent, float $total = 10000.00, string $status = 'pending_payment'): SalesRecord
    {
        return SalesRecord::factory()->create([
            'agent_id' => $agent->id,
            'is_credit' => true,
            'status' => 'approved',
            'credit_status' => $status,
            'total_value' => $total,
            'expected_collection_date' => now()->addDays(7)->toDateString(),
            'payment_proof_path' => 'proofs/test.jpg',
        ]);
    }

    public function test_partial_collection_creates_collection_and_marks_partially_collected(): void
    {
        $agent = User::factory()->communitySalesRepresentative()->create(['stock_balance' => 1000.00]);
        $accountant = User::factory()->accountant()->create();
        $record = $this->createApprovedCredit($agent);

        SalesRecordService::recordCollection($record, [
            'collected_amount' => 2000.00,
            'credit_notes' => 'First installment',
        ], $accountant->id);

        $record->refresh();
        $this->assertDatabaseHas('credit_collections', [
            'sales_record_id' => $record->id,
            'collected_amount' => 2000.00,
            'collected_by' => $accountant->id,
        ]);
        $this->assertEquals('partially_collected', $record->credit_status);
        $this->assertEquals(8000.00, $record->outstandingAmount());
        /* Partial collection should not yet credit the agent's stock balance. */
        $this->assertEquals(1000, (float) $agent->fresh()->stock_balance);
    }

    public function test_full_collection_marks_collected_and_credits_agent_stock(): void
    {
        $agent = User::factory()->communitySalesRepresentative()->create(['stock_balance' => 1000.00]);
        $accountant = User::factory()->accountant()->create();
        $record = $this->createApprovedCredit($agent, 5000.00);

        SalesRecordService::recordCollection($record, [
            'collected_amount' => 5000.00,
        ], $accountant->id);

        $record->refresh();
        $this->assertEquals('collected', $record->credit_status);
        $this->assertEquals(0, $record->outstandingAmount());
        $this->assertEquals(6000.00, (float) $agent->fresh()->stock_balance);
        $this->assertDatabaseHas('sales_records', [
            'id' => $record->id,
            'credit_status' => 'collected',
        ]);
    }

    public function test_collection_amount_greater_than_outstanding_is_rejected(): void
    {
        $agent = User::factory()->communitySalesRepresentative()->create();
        $accountant = User::factory()->accountant()->create();
        $record = $this->createApprovedCredit($agent, 5000.00);

        $this->expectException(ValidationException::class);

        SalesRecordService::recordCollection($record, [
            'collected_amount' => 7000.00,
        ], $accountant->id);
    }

    public function test_collection_requires_payment_proof(): void
    {
        $agent = User::factory()->communitySalesRepresentative()->create();
        $accountant = User::factory()->accountant()->create();
        $record = SalesRecord::factory()->create([
            'agent_id' => $agent->id,
            'is_credit' => true,
            'status' => 'approved',
            'credit_status' => 'pending_payment',
            'total_value' => 5000.00,
        ]);

        $this->expectException(ValidationException::class);
        SalesRecordService::recordCollection($record, [
            'collected_amount' => 5000.00,
        ], $accountant->id);
    }

    public function test_multiple_partial_collections_accumulate_until_collected(): void
    {
        $agent = User::factory()->communitySalesRepresentative()->create(['stock_balance' => 1000.00]);
        $accountant = User::factory()->accountant()->create();
        $record = $this->createApprovedCredit($agent, 5000.00);

        SalesRecordService::recordCollection($record, ['collected_amount' => 1500.00], $accountant->id);
        SalesRecordService::recordCollection($record, ['collected_amount' => 3500.00], $accountant->id);

        $record->refresh();
        $this->assertEquals('collected', $record->credit_status);
        $this->assertEquals(0, $record->outstandingAmount());
        $this->assertEquals(2, CreditCollection::where('sales_record_id', $record->id)->count());
        $this->assertEquals(6000.00, (float) $agent->fresh()->stock_balance);
    }
}
