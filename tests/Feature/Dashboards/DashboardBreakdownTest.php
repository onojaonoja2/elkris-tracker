<?php

namespace Tests\Feature\Dashboards;

use App\Filament\Pages\AccountantDashboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardBreakdownTest extends TestCase
{
    use RefreshDatabase;

    public function test_csr_dashboard_renders(): void
    {
        $user = User::factory()->communitySalesRepresentative()->create();

        $this->actingAs($user)
            ->get('/admin/csr-dashboard')
            ->assertOk();
    }

    public function test_agent_dashboard_renders(): void
    {
        $user = User::factory()->state(['role' => 'open_market'])->create();

        $this->actingAs($user)
            ->get('/admin/agent-dashboard')
            ->assertOk();
    }

    public function test_lead_dashboard_renders(): void
    {
        $user = User::factory()->lead()->create();

        $this->actingAs($user)
            ->get('/admin/lead-dashboard')
            ->assertOk();
    }

    public function test_sales_dashboard_renders(): void
    {
        $user = User::factory()->sales()->create();

        $this->actingAs($user)
            ->get('/admin/sales-orders-dashboard')
            ->assertOk();
    }

    public function test_manager_dashboard_renders(): void
    {
        $user = User::factory()->manager()->create();

        $this->actingAs($user)
            ->get('/admin/manager-dashboard')
            ->assertOk();
    }

    public function test_general_manager_dashboard_renders(): void
    {
        $user = User::factory()->generalManager()->create();

        $this->actingAs($user)
            ->get('/admin/general-manager-dashboard')
            ->assertOk();
    }

    public function test_accountant_dashboard_renders(): void
    {
        $user = User::factory()->accountant()->create();

        $this->actingAs($user)
            ->get('/admin/accountant-dashboard')
            ->assertOk();
    }

    public function test_accountant_dashboard_opens_office_sales_breakdown(): void
    {
        $user = User::factory()->accountant()->create();

        $this->actingAs($user);

        Livewire::test(AccountantDashboard::class)
            ->call('openOfficeSalesBreakdown')
            ->assertSet('breakdownType', 'office_sales');
    }
}
