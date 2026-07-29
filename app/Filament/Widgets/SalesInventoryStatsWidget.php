<?php

namespace App\Filament\Widgets;

use App\Models\AgentStock;
use App\Models\DamagedStockReturn;
use App\Models\SalesRecord;
use App\Models\StockTransfer;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SalesInventoryStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $userId = auth()->id();

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

        $todaySales = SalesRecord::where('agent_id', $userId)
            ->whereDate('created_at', today())
            ->where('status', 'approved')
            ->get();

        $todaySalesCount = $todaySales->count();
        $todaySalesValue = $todaySales->sum('total_value');

        return [
            Stat::make('Total Stock Units', number_format($totalStock))
                ->description('Units on hand')
                ->color($totalStock > 0 ? 'success' : 'danger')
                ->descriptionIcon('heroicon-o-cube'),

            Stat::make('Products', number_format($productCount))
                ->description('Distinct products in stock')
                ->color('info')
                ->descriptionIcon('heroicon-o-tag'),

            Stat::make("Today's Sales", number_format($todaySalesCount))
                ->description('₦'.number_format($todaySalesValue, 2).' value')
                ->color($todaySalesCount > 0 ? 'success' : 'gray')
                ->descriptionIcon('heroicon-o-currency-dollar'),

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
