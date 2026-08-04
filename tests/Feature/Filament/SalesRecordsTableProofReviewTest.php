<?php

namespace Tests\Feature\Filament;

use App\Models\SalesRecord;
use App\Models\User;
use App\Notifications\NewSubmissionNotification;
use App\Services\SalesRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SalesRecordsTableProofReviewTest extends TestCase
{
    use RefreshDatabase;

    private function createOutstandingApprovedCredit(User $agent): SalesRecord
    {
        return SalesRecord::factory()->create([
            'agent_id' => $agent->id,
            'is_credit' => true,
            'status' => 'approved',
            'credit_status' => 'pending_payment',
            'total_value' => 5000.00,
            'expected_collection_date' => now()->addDays(7)->toDateString(),
        ]);
    }

    public function test_agent_can_request_proof_review_for_own_credit_sale(): void
    {
        NotificationFacade::fake();

        $agent = User::factory()->communitySalesRepresentative()->create();
        $accountant = User::factory()->accountant()->create();
        $record = $this->createOutstandingApprovedCredit($agent);

        SalesRecordService::requestProofReview($record, $agent->id);

        $record->refresh();
        $this->assertNotNull($record->proof_review_requested_at);
        $this->assertEquals($agent->id, $record->proof_review_requested_by);
        $this->assertTrue($record->hasPendingProofReview());
    }

    public function test_agent_cannot_request_review_for_another_agents_sale(): void
    {
        $agent = User::factory()->communitySalesRepresentative()->create();
        $other = User::factory()->communitySalesRepresentative()->create();
        $record = $this->createOutstandingApprovedCredit($agent);

        $this->expectException(ValidationException::class);
        SalesRecordService::requestProofReview($record, $other->id);
    }

    public function test_requesting_review_notifies_accountant_and_admin_roles(): void
    {
        NotificationFacade::fake();

        $agent = User::factory()->communitySalesRepresentative()->create();
        $accountant = User::factory()->accountant()->create();
        $admin = User::factory()->admin()->create();
        $record = $this->createOutstandingApprovedCredit($agent);

        SalesRecordService::requestProofReview($record, $agent->id);

        NotificationFacade::assertSentTo($accountant, NewSubmissionNotification::class);
        NotificationFacade::assertSentTo($admin, NewSubmissionNotification::class);
    }

    public function test_uploading_payment_proof_clears_initial_proof_review_marker(): void
    {
        $agent = User::factory()->communitySalesRepresentative()->create();
        $accountant = User::factory()->accountant()->create();
        $record = $this->createOutstandingApprovedCredit($agent);

        SalesRecordService::requestProofReview($record, $agent->id);
        SalesRecordService::attachPaymentProof($record, ['payment_proof_path' => 'proofs/upload.jpg'], $accountant->id);

        $record->refresh();
        $this->assertNull($record->proof_review_requested_at);
        $this->assertNull($record->proof_review_requested_by);
        $this->assertFalse($record->hasPendingProofReview());
    }
}
