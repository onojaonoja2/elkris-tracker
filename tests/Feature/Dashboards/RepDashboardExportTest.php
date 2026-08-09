<?php

namespace Tests\Feature\Dashboards;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\User;
use App\Support\DashboardDateScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepDashboardExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_rep_export_query_finds_assigned_orders_in_selected_range(): void
    {
        $rep = User::factory()->rep()->create();
        $csr = User::factory()->communitySalesRepresentative()->create([
            'portfolio_agent_id' => $rep->id,
            'phone' => '08012345678',
        ]);
        $customer = Customer::factory()->create();

        $productType = ProductType::factory()->create([
            'name' => 'Ora herbal mix',
            'available_grammages' => ['500g'],
            'is_active' => true,
        ]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'user_id' => $rep->id,
            'status' => OrderStatus::Assigned,
            'total_price' => 2500,
            'assigned_to' => $csr->id,
            'assigned_by' => $rep->id,
            'assigned_at' => now(),
            'created_at' => now()->subHours(2),
        ]);

        Product::create([
            'order_id' => $order->id,
            'product_type_id' => $productType->id,
            'product_name' => 'Ora herbal mix',
            'grammage' => '500g',
            'quantity' => 2,
            'price' => 1250,
        ]);

        $this->actingAs($rep);

        session()->put('dashboard_date_from', now()->startOfDay()->toDateTimeString());
        session()->put('dashboard_date_to', now()->endOfDay()->toDateTimeString());

        [$from, $to] = DashboardDateScope::fromSession();

        $records = Order::where('user_id', $rep->id)
            ->where('is_migrated_order', false)
            ->whereNotNull('assigned_to')
            ->where('status', '!=', OrderStatus::Delivered)
            ->whereBetween('created_at', [$from, $to])
            ->with(['customer', 'assignedTo', 'products'])
            ->get();

        $this->assertCount(1, $records);
        $this->assertSame($csr->id, $records->first()->assigned_to);
        $this->assertSame('Ora herbal mix', $records->first()->products->first()->product_name);
    }
}
