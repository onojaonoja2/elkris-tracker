<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPriorityAndRejectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_priority_field_exists(): void
    {
        $user = User::create([
            'name' => 'Test Agent',
            'email' => 'agent@test.com',
            'password' => bcrypt('password'),
            'role' => 'field_agent',
            'my_id' => '123456',
        ]);

        $this->actingAs($user);

        $customer = Customer::create([
            'customer_name' => 'Test Customer',
            'phone_number' => '12345678901',
            'address' => 'Test Address',
            'city' => 'lagos_island',
            'state' => 'Lagos',
            'region' => 'South West',
            'priority' => 'high',
            'customer_status' => 'customer',
            'agent_id' => $user->id,
        ]);

        $this->assertEquals('high', $customer->priority);
        $this->assertEquals($user->id, $customer->agent_id);
    }

    public function test_rep_can_reject_customer(): void
    {
        $lead = User::create([
            'name' => 'Test Lead',
            'email' => 'lead@test.com',
            'password' => bcrypt('password'),
            'role' => 'lead',
            'my_id' => '234567',
        ]);

        $rep = User::create([
            'name' => 'Test Rep',
            'email' => 'rep@test.com',
            'password' => bcrypt('password'),
            'role' => 'rep',
            'lead_id' => $lead->id,
            'my_id' => '345678',
        ]);

        $customer = Customer::create([
            'customer_name' => 'Test Customer',
            'phone_number' => '12345678902',
            'address' => 'Test Address',
            'city' => 'lagos_island',
            'state' => 'Lagos',
            'region' => 'South West',
            'rep_id' => $rep->id,
            'lead_id' => $lead->id,
            'rep_acceptance_status' => 'pending',
            'customer_status' => 'customer',
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
