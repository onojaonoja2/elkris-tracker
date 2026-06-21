<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_field_agent_can_access_field_agent_dashboard(): void
    {
        $agent = User::factory()->fieldAgent()->create();

        $this->actingAs($agent);

        $response = $this->get('/admin/field-agent-dashboard');
        $response->assertStatus(200);
    }

    public function test_field_agent_cannot_access_admin_dashboard(): void
    {
        $agent = User::factory()->fieldAgent()->create();

        $this->actingAs($agent);

        $response = $this->get('/admin/manager-dashboard');
        $response->assertStatus(302);
    }

    public function test_admin_can_access_admin_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        $response = $this->get('/admin/manager-dashboard');
        $response->assertStatus(200);
    }

    public function test_unauthenticated_user_redirected_to_login(): void
    {
        $response = $this->get('/admin');
        $response->assertRedirect('/admin/login');
    }
}
