<?php

namespace Tests\Feature\Widgets;

use App\Filament\Widgets\CsrStocksWidget;
use App\Models\AgentStock;
use App\Models\ProductType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CsrStocksWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_csr_sees_only_their_own_stock(): void
    {
        $csr = User::factory()->communitySalesRepresentative()->create();
        $otherCsr = User::factory()->communitySalesRepresentative()->create();

        $productType = ProductType::factory()->create(['name' => 'Elkris Plantain Flour']);

        $mine = AgentStock::factory()->create([
            'user_id' => $csr->id,
            'product_type_id' => $productType->id,
            'product_name' => $productType->name,
            'grammage' => 1800,
            'quantity' => 45,
        ]);

        AgentStock::factory()->create([
            'user_id' => $otherCsr->id,
            'product_type_id' => $productType->id,
            'product_name' => $productType->name,
            'grammage' => 1800,
            'quantity' => 10,
        ]);

        $this->actingAs($csr);

        Livewire::test(CsrStocksWidget::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertTableColumnStateSet('quantity', 45, $mine);
    }

    public function test_my_stock_refreshes_after_refresh_dashboard_event(): void
    {
        $csr = User::factory()->communitySalesRepresentative()->create();
        $productType = ProductType::factory()->create(['name' => 'Elkris Plantain Flour']);

        $stock = AgentStock::factory()->create([
            'user_id' => $csr->id,
            'product_type_id' => $productType->id,
            'product_name' => $productType->name,
            'grammage' => 1800,
            'quantity' => 45,
        ]);

        $this->actingAs($csr);

        Livewire::test(CsrStocksWidget::class)
            ->assertTableColumnStateSet('quantity', 45, $stock);

        $stock->decrement('quantity', 6);

        Livewire::test(CsrStocksWidget::class)
            ->dispatch('refresh-dashboard')
            ->assertTableColumnStateSet('quantity', 39, $stock->fresh());
    }
}
