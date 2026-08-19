<?php

namespace Tests\Feature\Dashboards;

use App\Enums\StockTransferStatus;
use App\Livewire\RepLeadEntityBreakdownTable;
use App\Livewire\StockEntityBreakdownTable;
use App\Models\AgentStock;
use App\Models\Customer;
use App\Models\Order;
use App\Models\ProductType;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EntityBreakdownDrilldownTest extends TestCase
{
    use RefreshDatabase;

    public function test_rep_lead_entity_table_aggregates_orders_per_rep_and_lead(): void
    {
        $accountant = User::factory()->accountant()->create();
        $this->actingAs($accountant);

        $lead = User::factory()->lead()->create(['name' => 'Entity Lead']);
        $rep = User::factory()->rep()->create(['name' => 'Entity Rep', 'lead_id' => $lead->id]);

        $leadCustomer = Customer::factory()->leadId($lead)->create(['customer_name' => 'Lead Customer']);
        $repCustomer = Customer::factory()->repId($rep)->create(['customer_name' => 'Rep Customer']);

        Order::factory()->create([
            'customer_id' => $leadCustomer->id,
            'status' => 'delivered',
            'total_price' => 2000,
        ]);
        Order::factory()->create([
            'customer_id' => $repCustomer->id,
            'status' => 'delivered',
            'total_price' => 3000,
        ]);
        Order::factory()->create([
            'customer_id' => $repCustomer->id,
            'status' => 'delivered',
            'total_price' => 500,
        ]);

        Livewire::test(RepLeadEntityBreakdownTable::class)
            ->assertSee('Entity Lead')
            ->assertSee('₦2,000.00')
            ->assertSee('Entity Rep')
            ->assertSee('₦3,500.00')
            ->assertSee("dispatch('open-rep-sales-breakdown'", escape: false);
    }

    public function test_rep_lead_entity_table_filters_by_search(): void
    {
        $accountant = User::factory()->accountant()->create();
        $this->actingAs($accountant);

        $lead = User::factory()->lead()->create(['name' => 'Searchable Lead']);
        $other = User::factory()->lead()->create(['name' => 'Other Lead']);

        Livewire::test(RepLeadEntityBreakdownTable::class)
            ->set('search', 'Searchable')
            ->assertSee('Searchable Lead')
            ->assertDontSee('Other Lead');
    }

    public function test_stock_entity_table_aggregates_in_out_net_per_entity(): void
    {
        $accountant = User::factory()->accountant()->create();
        $this->actingAs($accountant);

        $agent = User::factory()->openMarket()->create(['name' => 'Entity Agent']);
        $productType = ProductType::factory()->create();
        $warehouse = Warehouse::factory()->create(['name' => 'Entity Warehouse']);

        AgentStock::create([
            'user_id' => $agent->id,
            'product_type_id' => $productType->id,
            'product_name' => 'Bitters',
            'grammage' => 200,
            'quantity' => 5,
        ]);

        $transfer = StockTransfer::create([
            'from_warehouse_id' => $warehouse->id,
            'to_agent_id' => $agent->id,
            'status' => StockTransferStatus::Received,
            'received_at' => now(),
            'requires_approval' => true,
        ]);
        $transfer->items()->create([
            'product_type_id' => $productType->id,
            'grammage' => 100,
            'quantity' => 10,
        ]);

        Livewire::test(StockEntityBreakdownTable::class)
            ->assertSee('Entity Agent')
            ->assertSee('Entity Warehouse')
            ->assertSee('+10')
            ->assertSee("dispatch('open-stock-movement-breakdown'", escape: false);
    }

    public function test_stock_entity_table_type_filter_shows_only_selected_entities(): void
    {
        $accountant = User::factory()->accountant()->create();
        $this->actingAs($accountant);

        $agent = User::factory()->openMarket()->create(['name' => 'Filter Agent']);
        $productType = ProductType::factory()->create();
        $warehouse = Warehouse::factory()->create(['name' => 'Filter Warehouse']);

        AgentStock::create([
            'user_id' => $agent->id,
            'product_type_id' => $productType->id,
            'product_name' => 'Bitters',
            'grammage' => 200,
            'quantity' => 5,
        ]);

        Livewire::test(StockEntityBreakdownTable::class)
            ->set('typeFilter', 'warehouse')
            ->assertSee('Filter Warehouse')
            ->assertDontSee('Filter Agent');
    }
}
