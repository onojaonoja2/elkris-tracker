<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\SalesRecords\SalesRecordResource;
use App\Filament\Resources\Stockists\StockistResource;
use App\Filament\Resources\StockTransactions\StockTransactionResource;
use App\Filament\Resources\TrialOrders\TrialOrderResource;
use App\Models\AgentStock;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\SalesRecord;
use App\Models\StockistStock;
use App\Models\TrialOrder;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class AccountantStatsOverviewWidget extends BaseWidget
{
    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    protected function getStats(): array
    {
        $pendingTrialOrders = TrialOrder::where('status', 'receipt_uploaded')
            ->whereNotNull('agent_id')
            ->count();

        $pendingSalesRecords = SalesRecord::where('status', 'receipt_uploaded')->count();

        $totalTrialOrdersValue = TrialOrder::where('status', 'approved')
            ->sum('total_value');

        $totalSalesRecordsValue = SalesRecord::where('status', 'approved')
            ->sum('total_value');

        $repSalesValue = Order::whereIn('status', ['delivered', 'confirmed', 'completed'])
            ->whereHas('customer', fn ($q) => $q->whereNotNull('rep_id'))
            ->sum('total_price');

        $totalWarehouseStock = Inventory::sum('quantity');

        $totalStockistStock = StockistStock::sum('quantity');

        $totalAgentStock = AgentStock::sum('quantity');

        $totalChannelValue = $totalTrialOrdersValue + $totalSalesRecordsValue;

        return [
            Stat::make('Pending Trial Orders', $pendingTrialOrders)
                ->description('Pending accountant verification')
                ->icon('heroicon-o-document-text')
                ->color('warning')
                ->url(TrialOrderResource::getUrl('index')),
            Stat::make('Pending Sales Records', $pendingSalesRecords)
                ->description('Pending accountant verification')
                ->icon('heroicon-o-receipt-percent')
                ->color('warning')
                ->url(SalesRecordResource::getUrl('index')),
            Stat::make('Channel Sales Value', self::formatCurrency($totalChannelValue))
                ->description('Approved trial orders + sales records')
                ->icon('heroicon-o-banknotes')
                ->color('success'),
            Stat::make('Rep Sales Value', self::formatCurrency($repSalesValue))
                ->description('Delivered/confirmed orders')
                ->icon('heroicon-o-shopping-bag')
                ->color('info')
                ->url(OrderResource::getUrl('index')),
            Stat::make('Warehouse Stock', number_format($totalWarehouseStock))
                ->description('Total units in all warehouses')
                ->icon('heroicon-o-building-storefront')
                ->color('primary')
                ->url(StockTransactionResource::getUrl('index')),
            Stat::make('Stockist Stock', number_format($totalStockistStock))
                ->description('Total units with stockists')
                ->icon('heroicon-o-archive-box')
                ->color('gray')
                ->url(StockistResource::getUrl('index')),
            Stat::make('Agent Stock', number_format($totalAgentStock))
                ->description('Total units with agents')
                ->icon('heroicon-o-users')
                ->color('gray'),
        ];
    }

    protected static function formatCurrency(float $amount): string
    {
        if ($amount >= 1000000000) {
            return '₦'.number_format($amount / 1000000000, 1).'B';
        }
        if ($amount >= 1000000) {
            return '₦'.number_format($amount / 1000000, 1).'M';
        }
        if ($amount >= 1000) {
            return '₦'.number_format($amount / 1000, 1).'K';
        }

        return '₦'.number_format($amount, 0);
    }

    public static function canView(): bool
    {
        return auth()->user()->role === 'accountant';
    }
}
