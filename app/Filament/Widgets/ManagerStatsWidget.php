<?php

namespace App\Filament\Widgets;

use App\Models\CallLog;
use App\Models\Customer;
use App\Models\Order;
use App\Models\StockistTransaction;
use App\Models\TrialOrder;
use App\Models\User;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ManagerStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $now = Carbon::now('Africa/Lagos');
        $todayStart = $now->copy()->startOfDay();

        $workStart = $now->copy()->setHour(8)->setMinute(0)->setSecond(0);
        $workEnd = $now->copy()->setHour(17)->setMinute(0)->setSecond(0);

        if ($now->lt($workStart)) {
            $dateFrom = $todayStart;
            $dateTo = $now->copy()->startOfDay()->addDay();
        } elseif ($now->gte($workEnd)) {
            $dateFrom = $workStart;
            $dateTo = $workEnd;
        } else {
            $dateFrom = $workStart;
            $dateTo = $now;
        }

        $leads = User::where('role', 'lead')->with('reps')->get();
        $leadIds = $leads->pluck('id');
        $repIds = User::whereIn('lead_id', $leadIds)->where('role', 'rep')->pluck('id');
        $agentIds = User::where('role', 'field_agent')->pluck('id');

        $totalCustomers = Customer::whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->count();

        $totalPortfolio = Customer::whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->whereHas('orders', fn ($q) => $q->where('status', '!=', 'cancelled'))
            ->count();

        $trialOrders = TrialOrder::whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->count();

        $stockTransactions = StockistTransaction::whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->count();

        $totalCalls = CallLog::whereDate('called_at', '>=', $dateFrom)
            ->whereDate('called_at', '<=', $dateTo)
            ->count();

        $totalOrders = Order::whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->where('status', '!=', 'cancelled')
            ->count();

        $totalRevenue = Order::whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->where('status', '!=', 'cancelled')
            ->sum('total_price');

        $repeatOrders = Order::whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->where('status', '!=', 'cancelled')
            ->whereHas('customer', fn ($q) => $q->whereHas('orders', fn ($sub) => $sub->where('id', '!=', \DB::raw('orders.id'))->where('status', '!=', 'cancelled')))
            ->count();

        $conversionRate = $totalCustomers > 0 ? round(($totalPortfolio / $totalCustomers) * 100, 1) : 0;

        return [
            Stat::make('Total Customers', $totalCustomers)
                ->description('New customers today')
                ->icon('heroicon-o-users')
                ->color('info')
                ->url(route('filament.admin.resources.customers.index'))
                ->openUrlInNewTab(),
            Stat::make('Portfolio', $totalPortfolio)
                ->description('Converted customers')
                ->icon('heroicon-o-user-group')
                ->color('success'),
            Stat::make('Trial Orders', $trialOrders)
                ->description('Trial orders today')
                ->icon('heroicon-o-beaker')
                ->color('warning')
                ->url(route('filament.admin.resources.trial-orders.index'))
                ->openUrlInNewTab(),
            Stat::make('Stockist', $stockTransactions)
                ->description('Stock transactions today')
                ->icon('heroicon-o-archive-box')
                ->color('danger')
                ->url(route('filament.admin.resources.stock-history.index'))
                ->openUrlInNewTab(),
            Stat::make('Calls Made', $totalCalls)
                ->description('Calls logged today')
                ->icon('heroicon-o-phone')
                ->color('primary')
                ->url(route('filament.admin.resources.call-logs.index'))
                ->openUrlInNewTab(),
            Stat::make('Repeat Orders', $repeatOrders)
                ->description('Repeated purchases')
                ->icon('heroicon-o-arrow-right')
                ->color('gray'),
            Stat::make('Orders', $totalOrders)
                ->description('Orders placed today')
                ->icon('heroicon-o-shopping-cart')
                ->color('info')
                ->url(route('filament.admin.resources.orders.index'))
                ->openUrlInNewTab(),
            Stat::make('Revenue', '₦'.number_format($totalRevenue, 2))
                ->description('Total revenue today')
                ->icon('heroicon-o-banknotes')
                ->color('success'),
        ];
    }

    public static function canView(): bool
    {
        return auth()->user()->role === 'manager' || auth()->user()->role === 'admin';
    }
}
