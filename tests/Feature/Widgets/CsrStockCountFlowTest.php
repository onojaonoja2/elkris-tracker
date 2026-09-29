<?php

namespace Tests\Feature\Widgets;

use App\Filament\Widgets\AccountantStockCountApprovalWidget;
use App\Filament\Widgets\SupervisorStockCountApprovalWidget;
use App\Models\AgentStock;
use App\Models\ProductType;
use App\Models\StockCount;
use App\Models\StockTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CsrStockCountFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_csr_initial_stock_count_replaces_existing_stock_after_verification_and_approval(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $accountant = User::factory()->accountant()->create();
        $csr = User::factory()->communitySalesRepresentative()->create();
        $productType = ProductType::factory()->create(['available_grammages' => [100, 200]]);

        AgentStock::create([
            'user_id' => $csr->id,
            'product_type_id' => $productType->id,
            'product_name' => $productType->name,
            'grammage' => 100,
            'quantity' => 50,
        ]);
        AgentStock::create([
            'user_id' => $csr->id,
            'product_type_id' => $productType->id,
            'product_name' => $productType->name,
            'grammage' => 200,
            'quantity' => 30,
        ]);

        $stockCount = $this->pendingCsrCount($csr, [
            'is_additional_count' => false,
        ], $productType, 100, 20);

        $this->actingAs($supervisor);

        Livewire::test(SupervisorStockCountApprovalWidget::class)
            ->callTableAction('supervisorVerify', $stockCount->id);

        $this->assertDatabaseHas('stock_counts', [
            'id' => $stockCount->id,
            'supervisor_status' => 'verified',
        ]);

        $this->actingAs($accountant);

        Livewire::test(AccountantStockCountApprovalWidget::class)
            ->callTableAction('accountantApprove', $stockCount->id);

        $this->assertDatabaseHas('agent_stocks', [
            'user_id' => $csr->id,
            'product_type_id' => $productType->id,
            'grammage' => 100,
            'quantity' => 20,
        ]);

        $this->assertDatabaseMissing('agent_stocks', [
            'user_id' => $csr->id,
            'product_type_id' => $productType->id,
            'grammage' => 200,
        ]);

        $this->assertSame(
            1,
            AgentStock::where('user_id', $csr->id)->count()
        );
    }

    public function test_csr_additional_stock_count_increments_once_after_verification_and_approval(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $accountant = User::factory()->accountant()->create();
        $csr = User::factory()->communitySalesRepresentative()->create();
        $productType = ProductType::factory()->create(['available_grammages' => [100, 200]]);

        AgentStock::create([
            'user_id' => $csr->id,
            'product_type_id' => $productType->id,
            'product_name' => $productType->name,
            'grammage' => 100,
            'quantity' => 10,
        ]);

        $stockCount = $this->pendingCsrCount($csr, [
            'is_additional_count' => true,
        ], $productType, 100, 5);

        $this->actingAs($supervisor);

        Livewire::test(SupervisorStockCountApprovalWidget::class)
            ->callTableAction('supervisorVerify', $stockCount->id);

        $this->actingAs($accountant);

        Livewire::test(AccountantStockCountApprovalWidget::class)
            ->callTableAction('accountantApprove', $stockCount->id);

        $this->assertDatabaseHas('agent_stocks', [
            'user_id' => $csr->id,
            'product_type_id' => $productType->id,
            'grammage' => 100,
            'quantity' => 15,
        ]);

        $this->assertSame(
            1,
            AgentStock::where('user_id', $csr->id)->count()
        );

        $this->assertSame(
            1,
            StockTransaction::where('disbursed_to', 'Additional stock count #'.$stockCount->id)->count()
        );
    }

    private function pendingCsrCount(User $csr, array $attributes, ProductType $productType, int $grammage, int $quantity): StockCount
    {
        $stockCount = StockCount::create(array_merge([
            'user_id' => $csr->id,
            'status' => 'pending',
            'supervisor_status' => null,
        ], $attributes));

        $stockCount->items()->create([
            'product_type_id' => $productType->id,
            'product_name' => $productType->name,
            'grammage' => $grammage,
            'quantity' => $quantity,
        ]);

        return $stockCount;
    }
}
