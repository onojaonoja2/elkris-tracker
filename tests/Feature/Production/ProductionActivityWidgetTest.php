<?php

namespace Tests\Feature\Production;

use App\Filament\Widgets\ProductionActivityWidget;
use App\Models\ProductionRun;
use App\Models\RawMaterial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductionActivityWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_widget_renders_for_manager(): void
    {
        $manager = User::factory()->manager()->create();
        RawMaterial::factory()->create([
            'quantity' => 5.0000,
            'reorder_level' => 10.0000,
        ]);
        ProductionRun::factory()->create(['status' => 'pending_review']);

        $this->actingAs($manager);

        Livewire::test(ProductionActivityWidget::class)
            ->assertSee('Low Stock Materials')
            ->assertSee('Pending Production Reviews');
    }

    public function test_widget_is_not_visible_for_sales_users(): void
    {
        $sales = User::factory()->sales()->create();

        $this->actingAs($sales);

        $this->assertFalse(ProductionActivityWidget::canView());
    }
}
