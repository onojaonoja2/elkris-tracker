<?php

namespace App\Filament\Widgets;

use App\Models\AgentStock;
use App\Models\CallLog;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\SalesRecord;
use App\Models\Stockist;
use App\Models\StockistStock;
use App\Models\StockistTransaction;
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
            ->whereHas('orders', fn ($q) => $q->where('status', '!=', 'cancelled'))
            ->count();

        $trialOrders = TrialOrder::whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->count();

        $pendingTrialOrders = TrialOrder::where('status', 'receipt_uploaded')->count();

        $salesRecords = SalesRecord::whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->count();

        $pendingSalesRecords = SalesRecord::where('status', 'receipt_uploaded')->count();

        $stockTxns = StockistTransaction::whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->count();

        $calls = CallLog::whereDate('called_at', '>=', $from)
            ->whereDate('called_at', '<=', $to)
            ->count();

        $orders = Order::whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->where('status', '!=', 'cancelled')
            ->count();

        $revenue = Order::whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->where('status', '!=', 'cancelled')
            ->sum('total_price');

        $conversionRate = $totalCustomers > 0 ? round(($convertedCustomers / $totalCustomers) * 100, 1) : 0;

        $totalStockists = Stockist::count();
        $totalAgents = User::whereIn('role', ['field_agent', 'direct_sales', 'open_market', 'retail_market'])->count();
        $totalTransfers = StockTransfer::whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->count();

        $warehouseStockUnits = Inventory::sum('quantity');
        $stockistStockUnits = StockistStock::sum('quantity');
        $agentStockUnits = AgentStock::sum('quantity');

        return [
            Stat::make('Total Customers', $totalCustomers)
                ->description($conversionRate.'% conversion rate')
                ->icon('heroicon-o-users')
                ->color('info'),
            Stat::make('Revenue', self::formatCurrency($revenue))
                ->description('Total revenue')
                ->icon('heroicon-o-banknotes')
                ->color('success'),
            Stat::make('Orders', $orders)
                ->description('Orders placed')
                ->icon('heroicon-o-shopping-cart')
                ->color('info'),
            Stat::make('Total Stockists', $totalStockists)
                ->description('Registered stockists')
                ->icon('heroicon-o-building-storefront')
                ->color('warning'),
            Stat::make('Total Agents', $totalAgents)
                ->description('Field agents & sales')
                ->icon('heroicon-o-user-group')
                ->color('primary'),
            Stat::make('Trial Orders', $trialOrders)
                ->description($pendingTrialOrders.' pending verification')
                ->icon('heroicon-o-beaker')
                ->color('warning'),
            Stat::make('Sales Records', $salesRecords)
                ->description($pendingSalesRecords.' pending verification')
                ->icon('heroicon-o-document-text')
                ->color('gray'),
            Stat::make('Calls Made', $calls)
                ->description('Calls logged')
                ->icon('heroicon-o-phone')
                ->color('primary'),
            Stat::make('Stock Transfers', $totalTransfers)
                ->description('All movements')
                ->icon('heroicon-o-arrows-right-left')
                ->color('danger'),
            Stat::make('Stockist Txns', $stockTxns)
                ->description('Stockist transactions')
                ->icon('heroicon-o-archive-box')
                ->color('danger'),
            Stat::make('Warehouse Stock', number_format($warehouseStockUnits).' units')
                ->description('Total inventory units')
                ->icon('heroicon-o-cube')
                ->color('info'),
            Stat::make('Stockist Stock', number_format($stockistStockUnits).' units')
                ->description('Total stockist units')
                ->icon('heroicon-o-building-office')
                ->color('warning'),
            Stat::make('Agent Stock', number_format($agentStockUnits).' units')
                ->description('Total agent units')
                ->icon('heroicon-o-user-group')
                ->color('success'),
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
        return auth()->user()->role === 'manager' || auth()->user()->role === 'admin';
    }
}
