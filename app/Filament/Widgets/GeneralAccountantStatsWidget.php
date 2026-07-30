<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\SalesRecords\SalesRecordResource;
use App\Filament\Resources\StockTransactions\StockTransactionResource;
use App\Models\AgentStock;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\SalesRecord;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class GeneralAccountantStatsWidget extends BaseWidget
{
    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    protected function getStats(): array
    {
        $totalCustomers = Customer::count();
        $convertedCustomers = Customer::whereHas('orders', fn ($q) => $q->where('status', '!=', OrderStatus::Cancelled)->where('is_migrated_order', false))->count();
        $conversionRate = $totalCustomers > 0 ? round(($convertedCustomers / $totalCustomers) * 100, 1) : 0;

        $orders = Order::where('status', '!=', OrderStatus::Cancelled)->where('is_migrated_order', false)->count();
        $revenue = Order::where('status', '!=', OrderStatus::Cancelled)->where('is_migrated_order', false)->sum('total_price');

        $pendingSalesRecords = SalesRecord::whereIn('status', ['pending', 'receipt_uploaded'])->count();
        $creditSalesOutstanding = SalesRecord::where('is_credit', true)
            ->where('status', 'approved')
            ->where('credit_status', 'pending_payment')
            ->sum('total_value');

        $warehouseStock = Inventory::sum('quantity');
        $agentStock = AgentStock::sum('quantity');

        return [
            Stat::make('Total Customers', $totalCustomers)
                ->description($conversionRate.'% conversion rate')
                ->icon('heroicon-o-users')
                ->color('info')
                ->url(CustomerResource::getUrl('index')),
            Stat::make('Revenue', self::formatCurrency($revenue))
                ->description('Total revenue')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->url(OrderResource::getUrl('index')),
            Stat::make('Orders', $orders)
                ->description('Orders placed')
                ->icon('heroicon-o-shopping-cart')
                ->color('primary')
                ->url(OrderResource::getUrl('index')),
            Stat::make('Pending Sales Records', $pendingSalesRecords)
                ->description('Awaiting verification')
                ->icon('heroicon-o-receipt-percent')
                ->color('warning')
                ->url(SalesRecordResource::getUrl('index')),
            Stat::make('Credit Outstanding', self::formatCurrency($creditSalesOutstanding))
                ->description('Pending credit collection')
                ->icon('heroicon-o-clock')
                ->color('danger'),
            Stat::make('Warehouse Stock', number_format($warehouseStock))
                ->description('Total units')
                ->icon('heroicon-o-building-storefront')
                ->color('info')
                ->url(StockTransactionResource::getUrl('index')),
            Stat::make('Agent Stock', number_format($agentStock))
                ->description('Total agent units')
                ->icon('heroicon-o-user-group')
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
        return auth()->user()->hasRole('general_accountant');
    }
}
