<?php

namespace Tests\Feature\Production;

use App\Models\ProductionRun;
use App\Models\RawMaterial;
use App\Models\User;
use App\Services\ProductionRunService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use OwenIt\Auditing\Contracts\Auditable;
use Tests\TestCase;

class ProductionRunTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_user_can_access_production_runs_page(): void
    {
        $user = User::factory()->productionManagement()->create();

        $this->actingAs($user)
            ->get('/admin/production-runs')
            ->assertOk();
    }

    public function test_run_creation_deducts_multiple_materials(): void
    {
        $user = User::factory()->productionManagement()->create();
        $materialA = RawMaterial::factory()->create([
            'quantity' => 200.0000,
            'unit_of_measure' => 'kg',
        ]);
        $materialB = RawMaterial::factory()->create([
            'quantity' => 100.0000,
            'unit_of_measure' => 'litres',
        ]);

        $run = ProductionRunService::create([
            'raw_materials' => [
                ['raw_material_id' => $materialA->id, 'quantity_used' => 50.0000],
                ['raw_material_id' => $materialB->id, 'quantity_used' => 25.0000],
            ],
            'production_date' => now()->format('Y-m-d'),
            'output_name' => 'Herbal Soap',
            'output_quantity' => 100.0000,
            'output_unit' => 'units',
            'created_by' => $user->id,
        ]);

        $this->assertDatabaseHas('production_runs', [
            'id' => $run->id,
            'status' => 'pending_review',
        ]);

        $this->assertCount(2, $run->fresh()->rawMaterials);
        $materialA->refresh();
        $materialB->refresh();
        $this->assertEquals(150.0000, $materialA->quantity);
        $this->assertEquals(75.0000, $materialB->quantity);
    }

    public function test_run_creation_fails_when_insufficient_stock(): void
    {
        $user = User::factory()->productionManagement()->create();
        $rawMaterial = RawMaterial::factory()->create([
            'quantity' => 10.0000,
            'unit_of_measure' => 'kg',
        ]);

        $this->expectException(ValidationException::class);

        ProductionRunService::create([
            'raw_materials' => [
                ['raw_material_id' => $rawMaterial->id, 'quantity_used' => 50.0000],
            ],
            'production_date' => now()->format('Y-m-d'),
            'output_name' => 'Herbal Soap',
            'output_quantity' => 100.0000,
            'output_unit' => 'units',
            'created_by' => $user->id,
        ]);
    }

    public function test_run_update_restores_old_and_applies_new_material_quantities(): void
    {
        $user = User::factory()->productionManagement()->create();
        $materialA = RawMaterial::factory()->create(['quantity' => 200.0000, 'unit_of_measure' => 'kg']);
        $materialB = RawMaterial::factory()->create(['quantity' => 100.0000, 'unit_of_measure' => 'kg']);

        $run = ProductionRunService::create([
            'raw_materials' => [
                ['raw_material_id' => $materialA->id, 'quantity_used' => 50.0000],
            ],
            'production_date' => now()->format('Y-m-d'),
            'output_name' => 'Herbal Soap',
            'output_quantity' => 100.0000,
            'output_unit' => 'units',
            'created_by' => $user->id,
        ]);

        ProductionRunService::update($run, [
            'raw_materials' => [
                ['raw_material_id' => $materialA->id, 'quantity_used' => 30.0000],
                ['raw_material_id' => $materialB->id, 'quantity_used' => 20.0000],
            ],
            'production_date' => now()->format('Y-m-d'),
            'output_name' => 'Herbal Soap',
            'output_quantity' => 100.0000,
            'output_unit' => 'units',
        ]);

        $materialA->refresh();
        $materialB->refresh();
        $this->assertEquals(170.0000, $materialA->quantity);
        $this->assertEquals(80.0000, $materialB->quantity);
        $this->assertCount(2, $run->fresh()->rawMaterials);
    }

    public function test_reviewed_run_cannot_be_updated(): void
    {
        $user = User::factory()->productionManagement()->create();
        $run = ProductionRun::factory()->reviewed()->create();
        $material = RawMaterial::factory()->create(['quantity' => 200.0000]);

        $this->expectException(ValidationException::class);

        ProductionRunService::update($run, [
            'raw_materials' => [
                ['raw_material_id' => $material->id, 'quantity_used' => 10.0000],
            ],
            'production_date' => now()->format('Y-m-d'),
            'output_name' => 'Herbal Soap',
            'output_quantity' => 100.0000,
            'output_unit' => 'units',
        ]);
    }

    public function test_accountant_can_review_production_run(): void
    {
        $accountant = User::factory()->accountant()->create();
        $run = ProductionRun::factory()->create([
            'status' => 'pending_review',
        ]);

        ProductionRunService::review($run, [
            'status' => 'reviewed',
            'accountant_notes' => 'Approved',
        ], $accountant->id);

        $run->refresh();
        $this->assertEquals('reviewed', $run->status);
        $this->assertEquals($accountant->id, $run->accountant_reviewed_by);
        $this->assertNotNull($run->accountant_reviewed_at);
    }

    public function test_reviewed_run_cannot_be_reviewed_again(): void
    {
        $accountant = User::factory()->accountant()->create();
        $run = ProductionRun::factory()->reviewed()->create();

        $this->expectException(ValidationException::class);

        ProductionRunService::review($run, [
            'status' => 'flagged',
        ], $accountant->id);
    }

    public function test_deleting_pending_run_restores_materials(): void
    {
        $user = User::factory()->productionManagement()->create();
        $material = RawMaterial::factory()->create(['quantity' => 200.0000]);

        $run = ProductionRunService::create([
            'raw_materials' => [
                ['raw_material_id' => $material->id, 'quantity_used' => 50.0000],
            ],
            'production_date' => now()->format('Y-m-d'),
            'output_name' => 'Herbal Soap',
            'output_quantity' => 100.0000,
            'output_unit' => 'units',
            'created_by' => $user->id,
        ]);

        $material->refresh();
        $this->assertEquals(150.0000, $material->quantity);

        $run->delete();

        $material->refresh();
        $this->assertEquals(200.0000, $material->quantity);
    }

    public function test_production_run_is_auditable(): void
    {
        $run = new ProductionRun;

        $this->assertInstanceOf(Auditable::class, $run);
    }
}
