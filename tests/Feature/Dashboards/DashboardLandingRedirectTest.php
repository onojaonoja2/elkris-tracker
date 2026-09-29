<?php

namespace Tests\Feature\Dashboards;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardLandingRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_root_redirects_to_manager_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin')
            ->assertRedirect('/admin/manager-dashboard');
    }

    public function test_manager_root_redirects_to_manager_dashboard(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->get('/admin')
            ->assertRedirect('/admin/manager-dashboard');
    }

    public function test_general_manager_root_redirects_to_general_manager_dashboard(): void
    {
        $generalManager = User::factory()->generalManager()->create();

        $this->actingAs($generalManager)
            ->get('/admin')
            ->assertRedirect('/admin/general-manager-dashboard');
    }

    public function test_general_accountant_root_redirects_to_general_accountant_dashboard(): void
    {
        $generalAccountant = User::factory()->state(['role' => 'general_accountant'])->create();

        $this->actingAs($generalAccountant)
            ->get('/admin')
            ->assertRedirect('/admin/general-accountant-dashboard');
    }
}
