<?php

namespace Tests\Feature\Widgets;

use App\Enums\StockTransferStatus;
use App\Filament\Widgets\SupervisorStatsWidget;
use App\Models\DamagedStockReturn;
use App\Models\ProductType;
use App\Models\StockCount;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SupervisorStatsWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_renders_pending_approval_counts(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $this->actingAs($supervisor);

        $this->createPendingTransfer();
        $this->createPendingTransfer();
        $this->createPendingCount();
        $this->createPendingCount();
        $this->createPendingCount();
        $this->createPendingDamagedReturn();

        Livewire::test(SupervisorStatsWidget::class)
            ->assertSee('Pending Stock Transfers')
            ->assertSeeText('2')
            ->assertSee('Pending Stock Counts')
            ->assertSeeText('3')
            ->assertSee('Damaged Returns Awaiting')
            ->assertSeeText('1');
    }

    public function test_cards_dispatch_approval_breakdown_events(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $this->actingAs($supervisor);

        Livewire::test(SupervisorStatsWidget::class)
            ->assertSee("type: 'stock_transfer'", escape: false)
            ->assertSee("type: 'stock_count'", escape: false)
            ->assertSee("type: 'damaged_return'", escape: false)
            ->assertSee("type: 'sales_records'", escape: false);
    }

    public function test_excludes_processed_records_from_counts(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $this->actingAs($supervisor);

        $this->createPendingTransfer();
        StockTransfer::create([
            'from_warehouse_id' => Warehouse::factory()->create()->id,
            'to_agent_id' => User::factory()->communitySalesRepresentative()->create()->id,
            'requested_by' => User::factory()->create()->id,
            'status' => StockTransferStatus::Approved,
            'requires_approval' => true,
            'supervisor_approved_by' => $supervisor->id,
            'supervisor_approved_at' => now(),
        ]);

        $this->createPendingCount();
        $verifiedAgent = User::factory()->communitySalesRepresentative()->create();
        StockCount::create([
            'user_id' => $verifiedAgent->id,
            'is_additional_count' => false,
            'status' => 'pending',
            'supervisor_status' => 'verified',
            'supervisor_verified_by' => $supervisor->id,
            'supervisor_verified_at' => now(),
        ]);

        $this->createPendingDamagedReturn();
        $approvedProductType = ProductType::factory()->create(['name' => fake()->unique()->word()]);
        DamagedStockReturn::factory()->approved()->create(['product_type_id' => $approvedProductType->id]);

        Livewire::test(SupervisorStatsWidget::class)
            ->assertSee('Pending Stock Transfers')
            ->assertSeeText('1')
            ->assertDontSeeText('2')
            ->assertSee('Pending Stock Counts')
            ->assertSeeText('1')
            ->assertSee('Damaged Returns Awaiting')
            ->assertSeeText('1');
    }

    private function createPendingTransfer(): StockTransfer
    {
        return StockTransfer::create([
            'from_warehouse_id' => Warehouse::factory()->create()->id,
            'to_agent_id' => User::factory()->communitySalesRepresentative()->create()->id,
            'requested_by' => User::factory()->communitySalesRepresentative()->create()->id,
            'status' => StockTransferStatus::Requested,
            'requires_approval' => true,
        ]);
    }

    private function createPendingCount(): StockCount
    {
        $agent = User::factory()->communitySalesRepresentative()->create();
        $productType = ProductType::factory()->create(['name' => fake()->unique()->word()]);

        $stockCount = StockCount::create([
            'user_id' => $agent->id,
            'is_additional_count' => false,
            'status' => 'pending',
            'supervisor_status' => null,
        ]);
        $stockCount->items()->create([
            'product_type_id' => $productType->id,
            'product_name' => $productType->name,
            'grammage' => 100,
            'quantity' => 5,
        ]);

        return $stockCount;
    }

    private function createPendingDamagedReturn(): DamagedStockReturn
    {
        $productType = ProductType::factory()->create(['name' => fake()->unique()->word()]);

        return DamagedStockReturn::factory()->create([
            'user_id' => User::factory()->communitySalesRepresentative()->create()->id,
            'status' => 'pending',
            'product_type_id' => $productType->id,
        ]);
    }
}
