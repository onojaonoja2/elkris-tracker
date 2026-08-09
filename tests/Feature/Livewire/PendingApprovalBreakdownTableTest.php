<?php

namespace Tests\Feature\Livewire;

use App\Enums\StockTransferStatus;
use App\Livewire\PendingApprovalBreakdownTable;
use App\Models\DamagedStockReturn;
use App\Models\ProductType;
use App\Models\SalesRecord;
use App\Models\StockCount;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PendingApprovalBreakdownTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_sees_pending_stock_transfer_breakdown(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $this->actingAs($supervisor);

        $requester = User::factory()->communitySalesRepresentative()->create(['name' => 'Ada Request']);
        $agent = User::factory()->communitySalesRepresentative()->create(['name' => 'Bob Agent']);
        $warehouse = Warehouse::factory()->create(['name' => 'Kano Warehouse']);
        $productType = ProductType::factory()->create(['name' => 'Rice']);

        $transfer = StockTransfer::create([
            'from_warehouse_id' => $warehouse->id,
            'to_agent_id' => $agent->id,
            'requested_by' => $requester->id,
            'status' => StockTransferStatus::Requested,
            'requires_approval' => true,
            'notes' => 'Urgent restock',
        ]);
        $transfer->items()->create([
            'product_type_id' => $productType->id,
            'grammage' => 500,
            'quantity' => 2,
        ]);

        Livewire::test(PendingApprovalBreakdownTable::class, ['type' => 'stock_transfer'])
            ->assertSee('#'.$transfer->id)
            ->assertSee('Ada Request')
            ->assertSee('Kano Warehouse')
            ->assertSee('Bob Agent')
            ->assertSee('2x Rice (500g)')
            ->assertSee('Urgent restock');
    }

    public function test_stock_transfer_breakdown_excludes_approved_and_non_approval_requests(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $this->actingAs($supervisor);

        $warehouse = Warehouse::factory()->create();
        $agent = User::factory()->communitySalesRepresentative()->create();

        $pending = StockTransfer::create([
            'from_warehouse_id' => $warehouse->id,
            'to_agent_id' => $agent->id,
            'requested_by' => User::factory()->communitySalesRepresentative()->create()->id,
            'status' => StockTransferStatus::Requested,
            'requires_approval' => true,
        ]);

        $approved = StockTransfer::create([
            'from_warehouse_id' => $warehouse->id,
            'to_agent_id' => $agent->id,
            'requested_by' => User::factory()->create()->id,
            'status' => StockTransferStatus::Approved,
            'requires_approval' => true,
            'supervisor_approved_by' => $supervisor->id,
            'supervisor_approved_at' => now(),
        ]);

        $notRequiringApproval = StockTransfer::create([
            'from_warehouse_id' => $warehouse->id,
            'to_agent_id' => $agent->id,
            'requested_by' => User::factory()->create()->id,
            'status' => StockTransferStatus::Requested,
            'requires_approval' => false,
        ]);

        Livewire::test(PendingApprovalBreakdownTable::class, ['type' => 'stock_transfer'])
            ->assertSee('#'.$pending->id)
            ->assertDontSee('#'.$approved->id)
            ->assertDontSee('#'.$notRequiringApproval->id);
    }

    public function test_supervisor_sees_pending_stock_count_breakdown(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $this->actingAs($supervisor);

        $agent = User::factory()->communitySalesRepresentative()->create(['name' => 'Carol Counter']);
        $productType = ProductType::factory()->create(['name' => 'Sugar']);

        $stockCount = StockCount::create([
            'user_id' => $agent->id,
            'is_additional_count' => false,
            'status' => 'pending',
            'supervisor_status' => null,
            'notes' => 'End of month count',
        ]);
        $stockCount->items()->create([
            'product_type_id' => $productType->id,
            'product_name' => $productType->name,
            'grammage' => 100,
            'quantity' => 12,
        ]);

        Livewire::test(PendingApprovalBreakdownTable::class, ['type' => 'stock_count'])
            ->assertSee('Carol Counter')
            ->assertSee('Initial')
            ->assertSee('12x Sugar (100g)')
            ->assertSee('End of month count');
    }

    public function test_stock_count_breakdown_excludes_verified_counts(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $this->actingAs($supervisor);

        $pendingAgent = User::factory()->communitySalesRepresentative()->create(['name' => 'Pending Count Agent']);
        $verifiedAgent = User::factory()->communitySalesRepresentative()->create(['name' => 'Verified Count Agent']);

        $pending = StockCount::create([
            'user_id' => $pendingAgent->id,
            'is_additional_count' => true,
            'status' => 'pending',
            'supervisor_status' => null,
        ]);

        $verified = StockCount::create([
            'user_id' => $verifiedAgent->id,
            'is_additional_count' => false,
            'status' => 'pending',
            'supervisor_status' => 'verified',
            'supervisor_verified_by' => $supervisor->id,
            'supervisor_verified_at' => now(),
        ]);

        Livewire::test(PendingApprovalBreakdownTable::class, ['type' => 'stock_count'])
            ->assertSee('Pending Count Agent')
            ->assertSeeText('Additional')
            ->assertDontSee('Verified Count Agent');
    }

    public function test_supervisor_sees_pending_damaged_return_breakdown(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $this->actingAs($supervisor);

        $user = User::factory()->communitySalesRepresentative()->create(['name' => 'Dave Returner']);
        $warehouse = Warehouse::factory()->create(['name' => 'Lagos Warehouse']);
        $productType = ProductType::factory()->create(['name' => 'African bitters']);

        $return = DamagedStockReturn::factory()->create([
            'user_id' => $user->id,
            'warehouse_id' => $warehouse->id,
            'product_type_id' => $productType->id,
            'grammage' => 250,
            'quantity' => 3,
            'reason' => 'Bottles cracked in transit',
            'status' => 'pending',
        ]);

        Livewire::test(PendingApprovalBreakdownTable::class, ['type' => 'damaged_return'])
            ->assertSee('Dave Returner')
            ->assertSee('Lagos Warehouse')
            ->assertSee('African bitters')
            ->assertSee('250g')
            ->assertSee('Bottles cracked in transit');
    }

    public function test_damaged_return_breakdown_excludes_approved_returns(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $this->actingAs($supervisor);

        $pendingUser = User::factory()->communitySalesRepresentative()->create(['name' => 'Pending Return User']);
        $approvedUser = User::factory()->communitySalesRepresentative()->create(['name' => 'Approved Return User']);
        $pendingProductType = ProductType::factory()->create(['name' => 'Pending Return Product']);
        $approvedProductType = ProductType::factory()->create(['name' => 'Approved Return Product']);

        DamagedStockReturn::factory()->create([
            'user_id' => $pendingUser->id,
            'product_type_id' => $pendingProductType->id,
            'status' => 'pending',
        ]);
        DamagedStockReturn::factory()->approved()->create([
            'user_id' => $approvedUser->id,
            'product_type_id' => $approvedProductType->id,
        ]);

        Livewire::test(PendingApprovalBreakdownTable::class, ['type' => 'damaged_return'])
            ->assertSee('Pending Return User')
            ->assertDontSee('Approved Return User');
    }

    public function test_search_filters_stock_transfer_records(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $this->actingAs($supervisor);

        $warehouse = Warehouse::factory()->create();
        $agent = User::factory()->communitySalesRepresentative()->create();

        $requesterA = User::factory()->communitySalesRepresentative()->create(['name' => 'Zara Alpha']);
        $requesterB = User::factory()->communitySalesRepresentative()->create(['name' => 'Yemi Beta']);

        $transferA = StockTransfer::create([
            'from_warehouse_id' => $warehouse->id,
            'to_agent_id' => $agent->id,
            'requested_by' => $requesterA->id,
            'status' => StockTransferStatus::Requested,
            'requires_approval' => true,
        ]);
        $transferB = StockTransfer::create([
            'from_warehouse_id' => $warehouse->id,
            'to_agent_id' => $agent->id,
            'requested_by' => $requesterB->id,
            'status' => StockTransferStatus::Requested,
            'requires_approval' => true,
        ]);

        Livewire::test(PendingApprovalBreakdownTable::class, ['type' => 'stock_transfer'])
            ->set('search', 'Zara')
            ->assertSee('#'.$transferA->id)
            ->assertDontSee('#'.$transferB->id);
    }

    public function test_supervisor_does_not_see_non_csr_stock_transfers(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $this->actingAs($supervisor);

        $warehouse = Warehouse::factory()->create();
        $agent = User::factory()->communitySalesRepresentative()->create();

        $nonCsr = StockTransfer::create([
            'from_warehouse_id' => $warehouse->id,
            'to_agent_id' => $agent->id,
            'requested_by' => User::factory()->openMarket()->create()->id,
            'status' => StockTransferStatus::Requested,
            'requires_approval' => true,
        ]);

        $csr = StockTransfer::create([
            'from_warehouse_id' => $warehouse->id,
            'to_agent_id' => $agent->id,
            'requested_by' => User::factory()->communitySalesRepresentative()->create()->id,
            'status' => StockTransferStatus::Requested,
            'requires_approval' => true,
        ]);

        Livewire::test(PendingApprovalBreakdownTable::class, ['type' => 'stock_transfer'])
            ->assertSee('#'.$csr->id)
            ->assertDontSee('#'.$nonCsr->id);
    }

    public function test_manager_sees_all_pending_stock_transfers_including_non_csr(): void
    {
        $manager = User::factory()->manager()->create();
        $this->actingAs($manager);

        $warehouse = Warehouse::factory()->create();
        $agent = User::factory()->communitySalesRepresentative()->create();

        $csr = StockTransfer::create([
            'from_warehouse_id' => $warehouse->id,
            'to_agent_id' => $agent->id,
            'requested_by' => User::factory()->communitySalesRepresentative()->create()->id,
            'status' => StockTransferStatus::Requested,
            'requires_approval' => true,
        ]);

        $openMarket = StockTransfer::create([
            'from_warehouse_id' => $warehouse->id,
            'to_agent_id' => $agent->id,
            'requested_by' => User::factory()->openMarket()->create()->id,
            'status' => StockTransferStatus::Requested,
            'requires_approval' => true,
        ]);

        Livewire::test(PendingApprovalBreakdownTable::class, ['type' => 'stock_transfer'])
            ->assertSee('#'.$csr->id)
            ->assertSee('#'.$openMarket->id);
    }

    public function test_supervisor_sees_pending_csr_sales_records_breakdown(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $this->actingAs($supervisor);

        $csr = User::factory()->communitySalesRepresentative()->create(['name' => 'Eve CSR']);
        $openMarket = User::factory()->openMarket()->create(['name' => 'Obi Open Market']);

        SalesRecord::factory()->create([
            'agent_id' => $csr->id,
            'agent_type' => 'community_sales_representative',
            'status' => 'pending',
            'supervisor_verified_at' => null,
            'total_value' => 5000.00,
            'is_credit' => false,
        ]);
        SalesRecord::factory()->create([
            'agent_id' => $openMarket->id,
            'agent_type' => 'open_market',
            'status' => 'pending',
            'supervisor_verified_at' => null,
            'total_value' => 7000.00,
            'is_credit' => false,
        ]);

        Livewire::test(PendingApprovalBreakdownTable::class, ['type' => 'sales_records'])
            ->assertSee('Eve CSR')
            ->assertDontSee('Obi Open Market');
    }

    public function test_manager_sees_open_retail_sales_records_breakdown(): void
    {
        $manager = User::factory()->manager()->create();
        $this->actingAs($manager);

        $csr = User::factory()->communitySalesRepresentative()->create(['name' => 'Eve CSR']);
        $retail = User::factory()->retailMarket()->create(['name' => 'Rita Retail']);

        SalesRecord::factory()->create([
            'agent_id' => $csr->id,
            'agent_type' => 'community_sales_representative',
            'status' => 'pending',
            'supervisor_verified_at' => null,
            'total_value' => 5000.00,
            'is_credit' => false,
        ]);
        SalesRecord::factory()->create([
            'agent_id' => $retail->id,
            'agent_type' => 'retail_market',
            'status' => 'pending',
            'supervisor_verified_at' => null,
            'total_value' => 7000.00,
            'is_credit' => false,
        ]);

        Livewire::test(PendingApprovalBreakdownTable::class, ['type' => 'sales_records'])
            ->assertSee('Rita Retail')
            ->assertDontSee('Eve CSR');
    }

    public function test_manager_sees_open_retail_stock_count_and_damaged_return_breakdowns(): void
    {
        $manager = User::factory()->manager()->create();
        $this->actingAs($manager);

        $csr = User::factory()->communitySalesRepresentative()->create(['name' => 'CSR Counter']);
        $openMarket = User::factory()->openMarket()->create(['name' => 'Open Market Counter']);

        $csrCount = StockCount::create([
            'user_id' => $csr->id,
            'is_additional_count' => false,
            'status' => 'pending',
            'supervisor_status' => null,
        ]);
        $openCount = StockCount::create([
            'user_id' => $openMarket->id,
            'is_additional_count' => false,
            'status' => 'pending',
            'supervisor_status' => null,
        ]);

        Livewire::test(PendingApprovalBreakdownTable::class, ['type' => 'stock_count'])
            ->assertSee('Open Market Counter')
            ->assertDontSee('CSR Counter');

        $productType = ProductType::factory()->create(['name' => 'Damaged test product']);
        DamagedStockReturn::factory()->create([
            'user_id' => $csr->id,
            'product_type_id' => $productType->id,
            'status' => 'pending',
        ]);
        DamagedStockReturn::factory()->create([
            'user_id' => $openMarket->id,
            'product_type_id' => $productType->id,
            'status' => 'pending',
        ]);

        Livewire::test(PendingApprovalBreakdownTable::class, ['type' => 'damaged_return'])
            ->assertSee('Open Market Counter')
            ->assertDontSee('CSR Counter');

        $this->assertNotNull($csrCount);
    }

    public function test_stock_transfer_breakdown_paginates_by_ten(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $this->actingAs($supervisor);

        $warehouse = Warehouse::factory()->create();
        $agent = User::factory()->communitySalesRepresentative()->create();

        $transfers = collect(range(1, 12))->map(function (int $i) use ($warehouse, $agent): StockTransfer {
            $transfer = StockTransfer::create([
                'from_warehouse_id' => $warehouse->id,
                'to_agent_id' => $agent->id,
                'requested_by' => User::factory()->communitySalesRepresentative()->create()->id,
                'status' => StockTransferStatus::Requested,
                'requires_approval' => true,
            ]);
            $transfer->created_at = now()->addMinutes($i);
            $transfer->save();

            return $transfer;
        });

        Livewire::test(PendingApprovalBreakdownTable::class, ['type' => 'stock_transfer'])
            ->assertSee('>#'.$transfers[11]->id.'<', false)
            ->assertSee('>#'.$transfers[2]->id.'<', false)
            ->assertDontSee('>#'.$transfers[0]->id.'<', false)
            ->assertDontSee('>#'.$transfers[1]->id.'<', false)
            ->call('setPage', 2)
            ->assertSee('>#'.$transfers[0]->id.'<', false)
            ->assertSee('>#'.$transfers[1]->id.'<', false)
            ->assertDontSee('>#'.$transfers[11]->id.'<', false);
    }

    public function test_non_supervisor_is_rejected(): void
    {
        $user = User::factory()->sales()->create();
        $this->actingAs($user);

        Livewire::test(PendingApprovalBreakdownTable::class)
            ->assertForbidden();
    }
}
