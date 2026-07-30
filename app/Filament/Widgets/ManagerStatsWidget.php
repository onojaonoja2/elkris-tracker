<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Enums\TrialOrderStatus;
use App\Filament\Resources\CallLogs\CallLogResource;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\SalesRecords\SalesRecordResource;
use App\Filament\Resources\StockTransactions\StockTransactionResource;
use App\Filament\Resources\StockTransfers\StockTransferResource;
use App\Filament\Resources\TrialOrders\TrialOrderResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\AgentStock;
use App\Models\CallLog;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\SalesRecord;
use App\Models\StockTransfer;
use App\Models\TrialOrder;
use App\Models\User;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\On;

class ManagerStatsWidget extends BaseWidget
{
    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    protected function getStats(): array
    {
        $now = Carbon::now('Africa/Lagos');

        $preset = Session::get('manager_date_preset', 'today');

        match ($preset) {
            'yesterday' => $from = $now->copy()->subDay()->startOfDay(),
            'this_week' => $from = $now->copy()->startOfWeek(),
            'this_month' => $from = $now->copy()->startOfMonth(),
            'lifetime' => $from = Carbon::now('Africa/Lagos')->subYears(10),
            default => $from = $now->copy()->setHour(8)->setMinute(0)->setSecond(0),
        };

        if ($preset !== 'lifetime') {
            if ($preset === 'yesterday') {
                $to = $now->copy()->subDay()->endOfDay();
            } elseif ($preset === 'this_week') {
                $to = $now->copy()->endOfWeek();
            } elseif ($preset === 'this_month') {
                $to = $now->copy()->endOfMonth();
            } else {
                $to = $now;
            }
        } else {
            $to = Carbon::now('Africa/Lagos');
        }

        $totalCustomers = Customer::whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->count();

        $convertedCustomers = Customer::whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->whereHas('orders', fn ($q) => $q->where('status', '!=', OrderStatus::Cancelled)->where('is_migrated_order', false))
            ->count();

        $trialOrders = TrialOrder::whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->count();

        $pendingTrialOrders = TrialOrder::where('status', TrialOrderStatus::ReceiptUploaded)->count();

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

        $conversionRate = $totalCustomers > 0 ? round(($convertedCustomers / $totalCustomers) * 100, 1) : 0;

        $totalAgents = User::whereIn('role', ['field_agent', 'community_sales_representative', 'open_market', 'retail_market'])->active()->count();
        $totalTransfers = StockTransfer::whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->count();

        $warehouseStockUnits = Inventory::sum('quantity');
        $agentStockUnits = AgentStock::sum('quantity');

        $creditSalesOutstanding = SalesRecord::where('is_credit', true)
            ->where('status', 'approved')
            ->where('credit_status', 'pending_payment')
            ->sum('total_value');

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
                ->color('info')
                ->url(OrderResource::getUrl('index')),
            Stat::make('Total Agents', $totalAgents)
                ->description('Field agents & sales')
                ->icon('heroicon-o-user-group')
                ->color('primary')
                ->url(UserResource::getUrl('index')),
            Stat::make('Trial Orders', $trialOrders)
                ->description($pendingTrialOrders.' pending verification')
                ->icon('heroicon-o-beaker')
                ->color('warning')
                ->url(TrialOrderResource::getUrl('index')),
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
                ->color('danger'),
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
