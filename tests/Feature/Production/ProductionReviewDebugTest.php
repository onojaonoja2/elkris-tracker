<?php

namespace Tests\Feature\Production;

use App\Filament\Resources\ProductionRuns\Pages\ManageProductionRuns;
use App\Filament\Widgets\ProductionRunsWidget;
use App\Models\Inventory;
use App\Models\ProductionRun;
use App\Models\ProductType;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\NewSubmissionNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductionReviewDebugTest extends TestCase
{
    use RefreshDatabase;

    private function makePendingRun(array $attributes = []): ProductionRun
    {
        $manager = User::factory()->productionManagement()->create();

        return ProductionRun::create(array_merge([
            'production_date' => now()->toDateString(),
            'output_name' => 'Test Output',
            'output_quantity' => 10,
            'output_unit' => 'kg',
            'status' => 'pending_review',
            'created_by' => $manager->id,
        ], $attributes));
    }

    public function test_accountant_can_review_from_resource_table(): void
    {
        $accountant = User::factory()->accountant()->create();
        $run = $this->makePendingRun();

        $this->actingAs($accountant);

        Livewire::test(ManageProductionRuns::class)
            ->callTableAction('reviewProductionRun', $run->id, [
                'status' => 'reviewed',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame('reviewed', $run->fresh()->status);
    }

    public function test_review_action_exists_on_dashboard_widget(): void
    {
        $accountant = User::factory()->accountant()->create();
        $run = $this->makePendingRun();

        $this->actingAs($accountant);

        Livewire::test(ProductionRunsWidget::class)
            ->assertTableActionExists('reviewProductionRun', record: $run->id);
    }

    public function test_accountant_can_review_from_dashboard_widget(): void
    {
        $accountant = User::factory()->accountant()->create();
        $run = $this->makePendingRun();

        $this->actingAs($accountant);

        Livewire::test(ProductionRunsWidget::class)
            ->callTableAction('reviewProductionRun', $run->id, [
                'status' => 'reviewed',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame('reviewed', $run->fresh()->status);
    }

    public function test_mapped_run_review_succeeds_without_production_warehouse_and_notifies_reviewer(): void
    {
        $accountant = User::factory()->accountant()->create();
        $productType = ProductType::factory()->create(['name' => 'Ora Herbal Mix']);

        $run = $this->makePendingRun([
            'product_type_id' => $productType->id,
            'grammage' => 100,
            'finished_quantity' => 5,
        ]);

        $this->actingAs($accountant);

        Livewire::test(ManageProductionRuns::class)
            ->callTableAction('reviewProductionRun', $run->id, [
                'status' => 'reviewed',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame('reviewed', $run->fresh()->status);
        $this->assertDatabaseMissing('inventories', ['product_type_id' => $productType->id]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $accountant->id,
            'type' => NewSubmissionNotification::class,
        ]);
    }

    public function test_mapped_run_review_posts_finished_goods_when_production_warehouse_designated(): void
    {
        $accountant = User::factory()->accountant()->create();
        $productType = ProductType::factory()->create(['name' => 'Ora Herbal Mix']);

        $store = Warehouse::factory()->create([
            'name' => 'Production Store',
            'type' => 'production',
            'is_active' => true,
        ]);

        $run = $this->makePendingRun([
            'product_type_id' => $productType->id,
            'grammage' => 100,
            'finished_quantity' => 5,
        ]);

        $this->actingAs($accountant);

        Livewire::test(ManageProductionRuns::class)
            ->callTableAction('reviewProductionRun', $run->id, [
                'status' => 'reviewed',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame('reviewed', $run->fresh()->status);

        $this->assertDatabaseHas('inventories', [
            'warehouse_id' => $store->id,
            'product_type_id' => $productType->id,
            'grammage' => 100,
            'quantity' => 5,
        ]);

        $this->assertDatabaseHas('stock_transactions', [
            'type' => 'received',
            'warehouse_id' => $store->id,
            'quantity' => 5,
        ]);

        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $accountant->id,
        ]);
    }

    public function test_flagged_review_does_not_post_finished_goods(): void
    {
        $accountant = User::factory()->accountant()->create();
        $productType = ProductType::factory()->create();

        $run = $this->makePendingRun([
            'product_type_id' => $productType->id,
            'grammage' => 100,
            'finished_quantity' => 5,
        ]);

        $this->actingAs($accountant);

        Livewire::test(ManageProductionRuns::class)
            ->callTableAction('reviewProductionRun', $run->id, [
                'status' => 'flagged',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame('flagged', $run->fresh()->status);
        $this->assertSame(0, Inventory::count());
    }
}
