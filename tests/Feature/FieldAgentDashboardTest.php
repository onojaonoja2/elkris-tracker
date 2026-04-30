<?php

namespace Tests\Feature;

use App\Filament\Widgets\FieldAgentDailySubmissionsWidget;
use App\Filament\Widgets\FieldAgentReplaceCustomersWidget;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FieldAgentDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_field_agent_can_access_dashboard(): void
    {
        $agent = User::create([
            'name' => 'Test Agent',
            'email' => 'agent@test.com',
            'password' => bcrypt('password'),
            'role' => 'field_agent',
            'my_id' => '123456',
        ]);

        $this->actingAs($agent);

        $response = $this->get('/admin/field-agent-dashboard');
        $response->assertStatus(200);
    }

    public function test_field_agent_sees_daily_submissions_widget(): void
    {
        $agent = User::create([
            'name' => 'Test Agent',
            'email' => 'agent2@test.com',
            'password' => bcrypt('password'),
            'role' => 'field_agent',
            'my_id' => '234567',
        ]);

        Customer::create([
            'customer_name' => 'Test Customer',
            'phone_number' => '12345678901',
            'address' => 'Test Address',
            'city' => 'lagos_island',
            'state' => 'Lagos',
            'region' => 'South West',
            'priority' => 'medium',
            'customer_status' => 'customer',
            'agent_id' => $agent->id,
            'created_at' => today(),
        ]);

        $this->actingAs($agent);

        // Test the widget directly using Livewire
        Livewire::test(FieldAgentDailySubmissionsWidget::class)
            ->assertSee('Customers Submitted Today');
    }

    public function test_replace_customer_phone_validation(): void
    {
        $agent = User::create([
            'name' => 'Test Agent',
            'email' => 'agent3@test.com',
            'password' => bcrypt('password'),
            'role' => 'field_agent',
            'my_id' => '345678',
        ]);

        $customer = Customer::create([
            'customer_name' => 'Old Customer',
            'phone_number' => '12345678901',
            'address' => 'Old Address',
            'city' => 'lagos_island',
            'state' => 'Lagos',
            'region' => 'South West',
            'priority' => 'medium',
            'customer_status' => 'customer',
            'agent_id' => $agent->id,
            'needs_replacement' => true,
        ]);

        $this->actingAs($agent);

        // Test with phone number less than 11 digits (should fail)
        Livewire::test(FieldAgentReplaceCustomersWidget::class)
            ->callTableAction('replaceWithNew', $customer->id, [
                'customer_name' => 'New Customer',
                'phone_number' => '1234567890', // 10 digits
                'address' => 'New Address',
                'priority' => 'high',
            ])
            ->assertHasTableActionErrors(['phone_number']);

        // Test with phone number more than 11 digits (should fail)
        Livewire::test(FieldAgentReplaceCustomersWidget::class)
            ->callTableAction('replaceWithNew', $customer->id, [
                'customer_name' => 'New Customer',
                'phone_number' => '123456789012', // 12 digits
                'address' => 'New Address',
                'priority' => 'high',
            ])
            ->assertHasTableActionErrors(['phone_number']);

        // Test with exactly 11 digits (should pass)
        Livewire::test(FieldAgentReplaceCustomersWidget::class)
            ->callTableAction('replaceWithNew', $customer->id, [
                'customer_name' => 'New Customer',
                'phone_number' => '12345678901', // 11 digits
                'address' => 'New Address',
                'priority' => 'high',
            ])
            ->assertHasNoTableActionErrors();
    }
}
