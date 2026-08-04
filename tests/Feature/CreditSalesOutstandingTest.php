<?php

namespace Tests\Feature;

use App\Models\SalesRecord;
use App\Models\User;
use App\Services\SalesRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditSalesOutstandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_outstanding_scope_includes_approved_credit_sales_with_null_credit_status(): void
    {
        $agent = User::factory()->communitySalesRepresentative()->create();

        SalesRecord::factory()->create([
            'agent_id' => $agent->id,
            'is_credit' => true,
            'status' => 'approved',
            'credit_status' => null,
            'total_value' => 15000.00,
        ]);

        $this->assertSame(15000.0, (float) SalesRecord::outstanding()->sum('total_value'));
        $this->assertSame(1, SalesRecord::outstanding()->count());
    }

    public function test_outstanding_scope_includes_pending_credit_sales(): void
    {
        $agent = User::factory()->communitySalesRepresentative()->create();

        SalesRecord::factory()->create([
            'agent_id' => $agent->id,
            'is_credit' => true,
            'status' => 'approved',
            'credit_status' => 'collected',
            'total_value' => 5000.00,
        ]);

        SalesRecord::factory()->create([
            'agent_id' => $agent->id,
            'is_credit' => true,
            'status' => 'pending',
            'credit_status' => 'pending_payment',
            'total_value' => 7000.00,
        ]);

        $this->assertSame(1, SalesRecord::outstanding()->count());
        $this->assertSame(7000.00, (float) SalesRecord::outstanding()->sum('total_value'));
    }

    public function test_outstanding_scope_excludes_collected_credit_sales(): void
    {
        $agent = User::factory()->communitySalesRepresentative()->create();

        SalesRecord::factory()->create([
            'agent_id' => $agent->id,
            'is_credit' => true,
            'status' => 'approved',
            'credit_status' => 'collected',
            'total_value' => 5000.00,
        ]);

        $this->assertSame(0, SalesRecord::outstanding()->count());
    }

    public function test_outstanding_scope_excludes_rejected_credit_sales(): void
    {
        $agent = User::factory()->communitySalesRepresentative()->create();

        SalesRecord::factory()->create([
            'agent_id' => $agent->id,
            'is_credit' => true,
            'status' => 'rejected',
            'credit_status' => 'pending_payment',
            'total_value' => 5000.00,
        ]);

        $this->assertSame(0, SalesRecord::outstanding()->count());
    }

    public function test_is_outstanding_returns_true_for_null_credit_status(): void
    {
        $agent = User::factory()->communitySalesRepresentative()->create();

        $record = SalesRecord::factory()->create([
            'agent_id' => $agent->id,
            'is_credit' => true,
            'status' => 'approved',
            'credit_status' => null,
        ]);

        $this->assertTrue($record->isOutstanding());
    }

    public function test_approve_normalizes_null_credit_status_to_pending_payment(): void
    {
        $agent = User::factory()->communitySalesRepresentative()->create();
        $accountant = User::factory()->accountant()->create();

        $record = SalesRecord::factory()->create([
            'agent_id' => $agent->id,
            'is_credit' => true,
            'status' => 'pending',
            'credit_status' => null,
            'total_value' => 8000.00,
        ]);

        SalesRecordService::approve($record, [], $accountant->id);

        $this->assertDatabaseHas('sales_records', [
            'id' => $record->id,
            'status' => 'approved',
            'credit_status' => 'pending_payment',
        ]);
    }

    public function test_approve_keeps_existing_credit_status(): void
    {
        $agent = User::factory()->communitySalesRepresentative()->create();
        $accountant = User::factory()->accountant()->create();

        $record = SalesRecord::factory()->create([
            'agent_id' => $agent->id,
            'is_credit' => true,
            'status' => 'pending',
            'credit_status' => 'partially_collected',
            'total_value' => 8000.00,
        ]);

        SalesRecordService::approve($record, [], $accountant->id);

        $this->assertDatabaseHas('sales_records', [
            'id' => $record->id,
            'status' => 'approved',
            'credit_status' => 'partially_collected',
        ]);
    }
}
