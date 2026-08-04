<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\SalesRecords\SalesRecordResource;
use App\Filament\Resources\StockTransactions\StockTransactionResource;
use App\Models\AgentStock;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\SalesRecord;
use App\Support\DashboardDateScope;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class AccountantStatsOverviewWidget extends BaseWidget
{
    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    protected function getStats(): array
    {
        [$from, $to] = DashboardDateScope::fromSession();

        $pendingSalesRecords = SalesRecord::whereIn('status', ['pending', 'receipt_uploaded'])
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $repSalesValue = Order::where('status', OrderStatus::Delivered)
            ->where('is_migrated_order', false)
            ->whereBetween('created_at', [$from, $to])
            ->whereHas('customer', fn ($q) => $q->whereNotNull('rep_id'))
            ->sum('total_price');

        $totalWarehouseStock = Inventory::sum('quantity');

        $totalAgentStock = AgentStock::sum('quantity');

        $totalChannelValue = SalesRecord::revenue(null, $from, $to);

        $creditSalesOutstanding = SalesRecord::outstanding()
            ->whereBetween('created_at', [$from, $to])
            ->sum('total_value');

        return [
            Stat::make('Pending Sales Records', $pendingSalesRecords)
                ->description('Pending accountant verification in selected range')
                ->icon('heroicon-o-receipt-percent')
                ->color('warning')
                ->url(SalesRecordResource::getUrl('index')),
            Stat::make('Credit Sales Outstanding', self::formatCurrency($creditSalesOutstanding))
                ->description('Pending credit collection in selected range')
                ->icon('heroicon-o-clock')
                ->color('danger')
                ->extraAttributes(['class' => 'cursor-pointer', 'wire:click' => "\$dispatch('open-credit-breakdown', { category: 'total' })"]),
            Stat::make('Channel Sales Value', self::formatCurrency($totalChannelValue))
                ->description('Approved sales records in selected range')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->extraAttributes(['class' => 'cursor-pointer', 'wire:click' => "\$dispatch('open-credit-breakdown', { category: 'total' })"]),
            Stat::make('Rep Sales Value', self::formatCurrency($repSalesValue))
                ->description('Delivered orders in selected range')
                ->icon('heroicon-o-shopping-bag')
                ->color('info')
                ->url(OrderResource::getUrl('index')),
            Stat::make('Warehouse Stock', number_format($totalWarehouseStock))
                ->description('Total units in all warehouses')
                ->icon('heroicon-o-building-storefront')
                ->color('primary')
                ->url(StockTransactionResource::getUrl('index')),
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
        return auth()->user()->hasAnyRole(['accountant', 'general_accountant']);
    }
}
