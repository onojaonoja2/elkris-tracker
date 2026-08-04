<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\AgentStock;
use App\Models\DamagedStockReturn;
use App\Models\Order;
use App\Models\SalesRecord;
use App\Models\StockTransfer;
use App\Support\DashboardDateScope;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class SalesInventoryStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    protected function getStats(): array
    {
        $userId = auth()->id();
        [$from, $to] = DashboardDateScope::fromSession();

        $totalStock = AgentStock::where('user_id', $userId)->sum('quantity');

        $productCount = AgentStock::where('user_id', $userId)
            ->where('quantity', '>', 0)
            ->distinct('product_name')
            ->count('product_name');

        $pendingRequests = StockTransfer::where('to_agent_id', $userId)
            ->whereIn('status', ['requested', 'pending'])
            ->count();

        $pendingReturns = DamagedStockReturn::where('user_id', $userId)
            ->where('status', 'pending')
            ->count();

        $salesQuery = SalesRecord::where('agent_id', $userId)
            ->whereBetween('created_at', [$from, $to])
            ->where('status', 'approved');

        $salesCount = $salesQuery->count();
        $salesValue = SalesRecord::revenue([$userId], $from, $to);

        $ordersQuery = Order::where('user_id', $userId)
            ->where('is_migrated_order', false)
            ->whereBetween('created_at', [$from, $to]);

        $totalOrders = $ordersQuery->count();
        $pendingOrders = (clone $ordersQuery)->where('status', OrderStatus::Pending)->count();
        $deliveredOrders = (clone $ordersQuery)->where('status', OrderStatus::Delivered)->count();
        $unassignedOrders = Order::where('user_id', $userId)
            ->where('is_migrated_order', false)
            ->whereNull('assigned_to')
            ->where('status', OrderStatus::Pending)
            ->whereBetween('created_at', [$from, $to])
            ->count();

        return [
            Stat::make('Total Stock Units', number_format($totalStock))
                ->description('Units on hand')
                ->color($totalStock > 0 ? 'success' : 'danger')
                ->descriptionIcon('heroicon-o-cube'),

            Stat::make('Products', number_format($productCount))
                ->description('Distinct products in stock')
                ->color('info')
                ->descriptionIcon('heroicon-o-tag'),

            Stat::make('Sales', number_format($salesCount))
                ->description('₦'.number_format($salesValue, 2).' value in selected range')
                ->color($salesCount > 0 ? 'success' : 'gray')
                ->descriptionIcon('heroicon-o-currency-dollar'),

            Stat::make('Total Orders', number_format($totalOrders))
                ->description('Pending: '.$pendingOrders.' | Delivered: '.$deliveredOrders)
                ->color('primary')
                ->descriptionIcon('heroicon-o-shopping-cart'),

            Stat::make('Unassigned Orders', number_format($unassignedOrders))
                ->description('Pending orders not yet assigned')
                ->color($unassignedOrders > 0 ? 'warning' : 'gray')
                ->descriptionIcon('heroicon-o-inbox-stack'),

            Stat::make('Pending Requests', number_format($pendingRequests))
                ->description('Stock requests awaiting approval')
                ->color($pendingRequests > 0 ? 'warning' : 'gray')
                ->descriptionIcon('heroicon-o-arrow-down-tray'),

            Stat::make('Pending Returns', number_format($pendingReturns))
                ->description('Damaged stock returns pending')
                ->color($pendingReturns > 0 ? 'warning' : 'gray')
                ->descriptionIcon('heroicon-o-exclamation-triangle'),
        ];
    }
}
