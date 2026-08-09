<?php

namespace Tests\Feature\Production;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_user_can_access_dashboard(): void
    {
        $user = User::factory()->productionManagement()->create();

        $this->actingAs($user)
            ->get('/admin/production-dashboard')
            ->assertOk();
    }

    public function test_non_production_user_is_redirected_from_dashboard(): void
    {
        $user = User::factory()->sales()->create();

        $this->actingAs($user)
            ->get('/admin/production-dashboard')
            ->assertRedirect('/admin');
    }
}
