<?php

namespace Tests\Feature;

use App\Filament\Widgets\FieldAgentDailySubmissionsWidget;
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
}
