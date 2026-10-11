<?php

namespace Tests\Feature\Production;

use App\Filament\Pages\WarehouseManagerDashboard;
use App\Filament\Widgets\RawMaterialReceiptApprovalsWidget;
use App\Models\RawMaterial;
use App\Models\RawMaterialReceipt;
use App\Models\RawMaterialStock;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\RawMaterialReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class RawMaterialReceiptApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function setupScenario(): array
    {
        $manager = User::factory()->warehouseManager()->create();
        $accountant = User::factory()->accountant()->create();

        $warehouse = Warehouse::factory()->create([
            'has_raw_material_module' => true,
            'is_active' => true,
            'manager_id' => $manager->id,
        ]);

        $material = RawMaterial::factory()->create([
            'name' => 'Procured Grain',
            'unit_of_measure' => 'kg',
            'quantity' => 0,
        ]);

        return [$manager, $accountant, $warehouse, $material];
    }

    public function test_procurement_receipt_approves_into_module_warehouse(): void
    {
        [$manager, $accountant, $warehouse, $material] = $this->setupScenario();

        $this->actingAs($manager);

        Livewire::test(WarehouseManagerDashboard::class)
            ->mountAction('receiveRawMaterials')
            ->fillForm([
                'warehouse_id' => $warehouse->id,
                'supplier_name' => 'Supplier ABC',
            ])
            ->set('mountedActions.0.data.items', [[
                'raw_material_id' => $material->id,
                'quantity' => 250,
            ]])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $receipt = RawMaterialReceipt::firstOrFail();
        $this->assertSame('pending', $receipt->status);
        $this->assertSame(0.0, (float) RawMaterialStock::sum('quantity'));

        $this->actingAs($accountant);

        Livewire::test(RawMaterialReceiptApprovalsWidget::class)
            ->assertSee('Supplier ABC')
            ->callTableAction('approveReceipt', $receipt->id)
            ->assertHasNoTableActionErrors();

        $this->assertSame('approved', $receipt->fresh()->status);
        $this->assertSame(250.0, (float) RawMaterialStock::where('warehouse_id', $warehouse->id)->first()->quantity);
        $this->assertSame(250.0, (float) $material->fresh()->quantity);
    }

    public function test_receipt_reject_moves_no_stock(): void
    {
        [$manager, $accountant, $warehouse, $material] = $this->setupScenario();

        $receipt = RawMaterialReceiptService::submit([
            'warehouse_id' => $warehouse->id,
            'supplier_name' => 'Supplier XYZ',
            'items' => [['raw_material_id' => $material->id, 'quantity' => 100]],
        ], $manager->id);

        $this->actingAs($accountant);

        Livewire::test(RawMaterialReceiptApprovalsWidget::class)
            ->callTableAction('rejectReceipt', $receipt->id, ['rejection_reason' => 'Wrong supplier'])
            ->assertHasNoTableActionErrors();

        $this->assertSame('rejected', $receipt->fresh()->status);
        $this->assertSame(0, RawMaterialStock::count());
        $this->assertSame(0.0, (float) $material->fresh()->quantity);
    }

    public function test_receipt_requires_module_warehouse_and_items(): void
    {
        [$manager, $accountant, $warehouse, $material] = $this->setupScenario();
        $plain = Warehouse::factory()->create(['manager_id' => $manager->id]);

        try {
            RawMaterialReceiptService::submit([
                'warehouse_id' => $plain->id,
                'supplier_name' => 'Supplier ABC',
                'items' => [['raw_material_id' => $material->id, 'quantity' => 10]],
            ], $manager->id);
            $this->fail('Expected ValidationException for non-module warehouse.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('warehouse_id', $e->errors());
        }

        try {
            RawMaterialReceiptService::submit([
                'warehouse_id' => $warehouse->id,
                'supplier_name' => 'Supplier ABC',
                'items' => [],
            ], $manager->id);
            $this->fail('Expected ValidationException for empty items.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('items', $e->errors());
        }
    }

    public function test_receipt_can_be_submitted_from_other_source(): void
    {
        [$manager, $accountant, $warehouse, $material] = $this->setupScenario();

        $this->actingAs($manager);

        Livewire::test(WarehouseManagerDashboard::class)
            ->mountAction('receiveRawMaterials')
            ->fillForm([
                'warehouse_id' => $warehouse->id,
                'source_type' => 'other',
                'source_name' => 'Donation from Partner NGO',
                'notes' => 'Received in bags',
            ])
            ->set('mountedActions.0.data.items', [[
                'raw_material_id' => $material->id,
                'quantity' => 150,
            ]])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $receipt = RawMaterialReceipt::firstOrFail();
        $this->assertSame('other', $receipt->source_type);
        $this->assertSame('Donation from Partner NGO', $receipt->supplier_name);
        $this->assertSame('pending', $receipt->status);
        $this->assertSame(0, RawMaterialStock::count());

        $this->actingAs($accountant);

        Livewire::test(RawMaterialReceiptApprovalsWidget::class)
            ->assertSee('Donation from Partner NGO')
            ->assertSee('Other')
            ->callTableAction('approveReceipt', $receipt->id)
            ->assertHasNoTableActionErrors();

        $this->assertSame('approved', $receipt->fresh()->status);
        $this->assertSame(150.0, (float) RawMaterialStock::where('warehouse_id', $warehouse->id)->first()->quantity);
    }

    public function test_receipt_from_other_source_requires_source_name(): void
    {
        [$manager, $accountant, $warehouse, $material] = $this->setupScenario();

        try {
            RawMaterialReceiptService::submit([
                'warehouse_id' => $warehouse->id,
                'source_type' => 'other',
                'source_name' => '   ',
                'items' => [['raw_material_id' => $material->id, 'quantity' => 10]],
            ], $manager->id);
            $this->fail('Expected ValidationException for missing source name.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('source_name', $e->errors());
        }

        $this->assertSame(0, RawMaterialReceipt::count());
    }

    public function test_receipt_stores_dispatch_papers_for_other_source(): void
    {
        [$manager, $accountant, $warehouse, $material] = $this->setupScenario();

        $receipt = RawMaterialReceiptService::submit([
            'warehouse_id' => $warehouse->id,
            'source_type' => 'other',
            'source_name' => 'Return to stock',
            'dispatch_papers' => ['dispatch-papers/note-1.pdf', 'dispatch-papers/photo-1.png'],
            'items' => [['raw_material_id' => $material->id, 'quantity' => 10]],
        ], $manager->id);

        $this->assertEqualsCanonicalizing(
            ['dispatch-papers/note-1.pdf', 'dispatch-papers/photo-1.png'],
            $receipt->fresh()->dispatchPapers()
        );
    }

    public function test_movement_report_export_downloads(): void
    {
        [$manager, $accountant, $warehouse, $material] = $this->setupScenario();

        $receipt = RawMaterialReceiptService::submit([
            'warehouse_id' => $warehouse->id,
            'supplier_name' => 'Supplier ABC',
            'items' => [['raw_material_id' => $material->id, 'quantity' => 250]],
        ], $manager->id);

        RawMaterialReceiptService::approve($receipt, $accountant->id);

        session()->put('dashboard_date_from', now()->subDay()->toDateTimeString());
        session()->put('dashboard_date_to', now()->addDay()->toDateTimeString());

        $this->actingAs($accountant);

        Livewire::test(RawMaterialReceiptApprovalsWidget::class)
            ->callTableAction('exportMovements', null, [
                'warehouse_id' => $warehouse->id,
            ])
            ->assertFileDownloaded();
    }

    public function test_manager_sees_approval_widget(): void
    {
        $manager = User::factory()->manager()->create();
        $this->actingAs($manager);

        Livewire::test(RawMaterialReceiptApprovalsWidget::class)
            ->assertOk()
            ->assertSee('Raw Material Receipts');
    }
}
