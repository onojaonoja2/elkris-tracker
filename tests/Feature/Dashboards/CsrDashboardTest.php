<?php

namespace Tests\Feature\Dashboards;

use App\Filament\Pages\CsrDashboard;
use App\Models\ProductType;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CsrDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_csr_additional_stock_count_adds_to_stock_and_records_transaction(): void
    {
        Setting::setValue('stock_at_hand_enabled', '1');

        $csr = User::factory()->communitySalesRepresentative()->create();
        $productType = ProductType::factory()->create(['available_grammages' => [100, 200, 500]]);

        $this->actingAs($csr);

        Livewire::test(CsrDashboard::class)
            ->mountAction('submitStockCount')
            ->set('mountedActions.0.data.is_additional_count', true)
            ->set('mountedActions.0.data.items', [[
                'product_type_id' => $productType->id,
                'grammage' => '100',
                'quantity' => 7,
            ]])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('stock_counts', [
            'user_id' => $csr->id,
            'is_additional_count' => 1,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('agent_stocks', [
            'user_id' => $csr->id,
            'product_type_id' => $productType->id,
            'grammage' => 100,
            'quantity' => 7,
        ]);

        $this->assertDatabaseHas('stock_transactions', [
            'type' => 'received',
            'product_type_id' => $productType->id,
            'quantity' => 7,
            'user_id' => $csr->id,
        ]);
    }
}
