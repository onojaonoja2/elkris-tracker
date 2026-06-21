<?php

namespace Tests\Feature;

use App\Enums\CustomerPriority;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPriorityAndRejectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_priority_field_exists(): void
    {
        $user = User::factory()->fieldAgent()->create([
            'name' => 'Test Agent',
            'my_id' => '123456',
        ]);

        $this->actingAs($user);

        $customer = Customer::factory()->agentId($user)->create([
            'priority' => CustomerPriority::High,
        ]);

        $this->assertEquals(CustomerPriority::High, $customer->priority);
        $this->assertEquals($user->id, $customer->agent_id);
    }

    public function test_rep_can_reject_customer(): void
    {
        $lead = User::factory()->lead()->create([
            'name' => 'Test Lead',
            'my_id' => '234567',
        ]);

        $rep = User::factory()->rep()->create([
            'name' => 'Test Rep',
            'lead_id' => $lead->id,
            'my_id' => '345678',
        ]);

        $customer = Customer::factory()
            ->leadId($lead)
            ->repId($rep)
            ->create([
                'rep_acceptance_status' => 'pending',
            ]);

        $customer->update([
            'rep_id' => null,
            'rep_acceptance_status' => 'rejected',
            'rejected_at' => now(),
            'rejected_by' => $rep->id,
            'rejection_note' => 'Not interested',
        ]);

        $this->assertEquals('rejected', $customer->fresh()->rep_acceptance_status);
        $this->assertEquals($lead->id, $customer->fresh()->lead_id);
    }
}
