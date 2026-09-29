<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Enums\StockTransferStatus;
use App\Filament\Resources\CallLogs\CallLogResource;
use App\Filament\Resources\SalesRecords\SalesRecordResource;
use App\Filament\Resources\StockTransactions\StockTransactionResource;
use App\Filament\Resources\StockTransfers\StockTransferResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\AgentStock;
use App\Models\CallLog;
use App\Models\Customer;
use App\Models\DamagedStockReturn;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\SalesRecord;
use App\Models\StockCount;
use App\Models\StockTransfer;
use App\Models\User;
use App\Support\DashboardDateScope;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class ManagerStatsWidget extends BaseWidget
{
    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    protected function getStats(): array
    {
        [$from, $to] = DashboardDateScope::fromSession();

        $totalCustomers = Customer::count();

        $customersAddedToday = Customer::whereDate('created_at', today())->count();

        $salesRecords = SalesRecord::whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->count();

        $pendingSalesRecords = SalesRecord::whereIn('status', ['pending', 'receipt_uploaded'])->count();

        $calls = CallLog::whereDate('called_at', '>=', $from)
            ->whereDate('called_at', '<=', $to)
            ->count();

        $orders = Order::whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->where('status', '!=', OrderStatus::Cancelled)
            ->where('is_migrated_order', false)
            ->count();

        $revenue = Order::whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->where('status', '!=', OrderStatus::Cancelled)
            ->where('is_migrated_order', false)
            ->sum('total_price');

        $totalAgents = User::whereIn('role', ['field_agent', 'community_sales_representative', 'open_market', 'retail_market'])->active()->count();
        $totalTransfers = StockTransfer::whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->count();

        $warehouseStockUnits = Inventory::sum('quantity');
        $agentStockUnits = AgentStock::sum('quantity');

        $creditSalesOutstanding = SalesRecord::outstanding()->sum('total_value');

        $pendingTransfers = StockTransfer::where('status', StockTransferStatus::Requested)
            ->whereNull('supervisor_approved_by')
            ->where('requires_approval', true)
            ->count();

        $pendingOpenRetailSales = SalesRecord::whereIn('status', ['pending', 'receipt_uploaded'])
            ->whereNull('supervisor_verified_at')
            ->whereIn('agent_type', ['open_market', 'retail_market'])
            ->count();

        $pendingOpenRetailCounts = StockCount::where('status', 'pending')
            ->whereNull('supervisor_status')
            ->whereHas('user', fn ($q) => $q->whereIn('role', ['open_market', 'retail_market']))
            ->count();

        $pendingOpenRetailDamaged = DamagedStockReturn::where('status', 'pending')
            ->whereNull('supervisor_approved_by')
            ->whereHas('user', fn ($q) => $q->whereIn('role', ['open_market', 'retail_market']))
            ->count();

        return [
            Stat::make('Total Customers', $totalCustomers)
                ->icon('heroicon-o-users')
                ->color('info')
                ->extraAttributes(['class' => 'cursor-pointer', 'wire:click' => "\$dispatch('open-customer-breakdown')"]),
            Stat::make('Customers Added Today', $customersAddedToday)
                ->description('Added today')
                ->icon('heroicon-o-user-plus')
                ->color('success')
                ->extraAttributes(['class' => 'cursor-pointer', 'wire:click' => "\$dispatch('open-customers-added-today')"]),
            Stat::make('Revenue', self::formatCurrency($revenue))
                ->description('Total revenue')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->extraAttributes(['class' => 'cursor-pointer', 'wire:click' => "\$dispatch('open-order-breakdown', { category: 'total' })"]),
            Stat::make('Orders', $orders)
                ->description('Orders placed')
                ->icon('heroicon-o-shopping-cart')
                ->color('info')
                ->extraAttributes(['class' => 'cursor-pointer', 'wire:click' => "\$dispatch('open-order-breakdown', { category: 'total' })"]),
            Stat::make('Total Agents', $totalAgents)
                ->description('Field agents & sales')
                ->icon('heroicon-o-user-group')
                ->color('primary')
                ->url(UserResource::getUrl('index')),
            Stat::make('Sales Records', $salesRecords)
                ->description($pendingSalesRecords.' pending verification')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->url(SalesRecordResource::getUrl('index')),
            Stat::make('Calls Made', $calls)
                ->description('Calls logged')
                ->icon('heroicon-o-phone')
                ->color('primary')
                ->url(CallLogResource::getUrl('index')),
            Stat::make('Stock Transfers', $totalTransfers)
                ->description('All movements')
                ->icon('heroicon-o-arrows-right-left')
                ->color('danger')
                ->url(StockTransferResource::getUrl('index')),
            Stat::make('Warehouse Stock', number_format($warehouseStockUnits).' units')
                ->description('Total inventory units')
                ->icon('heroicon-o-cube')
                ->color('info')
                ->url(StockTransactionResource::getUrl('index')),
            Stat::make('Agent Stock', number_format($agentStockUnits).' units')
                ->description('Total agent units')
                ->icon('heroicon-o-user-group')
                ->color('success')
                ->url(StockTransactionResource::getUrl('index')),
            Stat::make('Credit Sales', self::formatCurrency($creditSalesOutstanding))
                ->description('Pending credit collection')
                ->icon('heroicon-o-clock')
                ->color('danger')
                ->extraAttributes(['class' => 'cursor-pointer', 'wire:click' => "\$dispatch('open-credit-breakdown', { category: 'total' })"]),
            Stat::make('Pending Stock Transfers', $pendingTransfers)
                ->description('All transfers awaiting action')
                ->icon('heroicon-o-arrows-right-left')
                ->color('warning')
                ->extraAttributes(['class' => 'cursor-pointer', 'wire:click' => "\$dispatch('open-approval-breakdown', { type: 'stock_transfer' })"]),
            Stat::make('Open Market Sales', $pendingOpenRetailSales)
                ->description('Pending verification')
                ->icon('heroicon-o-document-text')
                ->color('warning')
                ->extraAttributes(['class' => 'cursor-pointer', 'wire:click' => "\$dispatch('open-approval-breakdown', { type: 'sales_records' })"]),
            Stat::make('Open Market Counts', $pendingOpenRetailCounts)
                ->description('Pending stock counts')
                ->icon('heroicon-o-clipboard-document-list')
                ->color('warning')
                ->extraAttributes(['class' => 'cursor-pointer', 'wire:click' => "\$dispatch('open-approval-breakdown', { type: 'stock_count' })"]),
            Stat::make('Damaged Returns', $pendingOpenRetailDamaged)
                ->description('Open market returns')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->extraAttributes(['class' => 'cursor-pointer', 'wire:click' => "\$dispatch('open-approval-breakdown', { type: 'damaged_return' })"]),
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
        return auth()->user()->hasAnyRole(['admin', 'manager', 'general_manager']);
    }
}
