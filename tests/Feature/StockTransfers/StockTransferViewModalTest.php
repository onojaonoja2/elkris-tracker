<?php

namespace Tests\Feature\StockTransfers;

use App\Filament\Resources\StockTransfers\Pages\ListStockTransfers;
use App\Filament\Resources\StockTransfers\StockTransferResource;
use App\Models\ProductType;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

class StockTransferViewModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_foreign_key_fields_resolve_to_related_names(): void
    {
        $transfer = StockTransfer::factory()->create(['status' => 'requested']);

        $ref = new ReflectionMethod(StockTransferResource::class, 'relationNameForIdField');
        $ref->setAccessible(true);

        $this->assertSame('fromWarehouse', $ref->invoke(null, $transfer, 'from_warehouse_id'));
        $this->assertSame('toAgent', $ref->invoke(null, $transfer, 'to_agent_id'));
        $this->assertSame('dispatcher', $ref->invoke(null, $transfer, 'dispatched_by'));
        $this->assertSame('requester', $ref->invoke(null, $transfer, 'requested_by'));
        $this->assertSame('receiver', $ref->invoke(null, $transfer, 'received_by'));
        $this->assertNull($ref->invoke(null, $transfer, 'missing_relation_id'));
    }

    public function test_view_modal_shows_related_names_instead_of_raw_ids(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $warehouse = Warehouse::factory()->create(['name' => 'Main Hub']);
        $agent = User::factory()->communitySalesRepresentative()->create(['name' => 'Jude Agent']);
        $requester = User::factory()->supervisor()->create(['name' => 'Sara Supervisor']);

        $transfer = StockTransfer::factory()->create([
            'from_warehouse_id' => $warehouse->id,
            'to_agent_id' => $agent->id,
            'requested_by' => $requester->id,
            'status' => 'requested',
        ]);

        Livewire::test(ListStockTransfers::class)
            ->mountTableAction('view', $transfer->id)
            ->assertHasNoActionErrors()
            ->assertMountedActionModalSee('Main Hub')
            ->assertMountedActionModalSee('Jude Agent')
            ->assertMountedActionModalSee('Sara Supervisor');
    }

    public function test_view_modal_shows_product_name_for_transfer_items(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $productType = ProductType::factory()->create(['name' => 'African bitters']);
        $transfer = StockTransfer::factory()->create(['status' => 'requested']);

        $transfer->items()->create([
            'product_type_id' => $productType->id,
            'grammage' => 500,
            'quantity' => 5,
        ]);

        Livewire::test(ListStockTransfers::class)
            ->mountTableAction('view', $transfer->id)
            ->assertHasNoActionErrors()
            ->assertMountedActionModalSee('African bitters');
    }
}
