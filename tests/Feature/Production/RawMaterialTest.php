<?php

namespace Tests\Feature\Production;

use App\Models\RawMaterial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RawMaterialTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_user_can_access_raw_materials_page(): void
    {
        $user = User::factory()->productionManagement()->create();

        $this->actingAs($user)
            ->get('/admin/raw-materials')
            ->assertOk();
    }

    public function test_non_production_user_cannot_access_raw_materials_page(): void
    {
        $user = User::factory()->sales()->create();

        $this->actingAs($user)
            ->get('/admin/raw-materials')
            ->assertForbidden();
    }

    public function test_raw_material_low_stock_scope(): void
    {
        RawMaterial::factory()->create([
            'name' => 'Low Item',
            'quantity' => 10.0000,
            'reorder_level' => 50.0000,
        ]);

        RawMaterial::factory()->create([
            'name' => 'Okay Item',
            'quantity' => 100.0000,
            'reorder_level' => 50.0000,
        ]);

        $lowStock = RawMaterial::whereNotNull('reorder_level')
            ->whereColumn('quantity', '<=', 'reorder_level')
            ->pluck('name');

        $this->assertCount(1, $lowStock);
        $this->assertTrue($lowStock->contains('Low Item'));
    }
}
