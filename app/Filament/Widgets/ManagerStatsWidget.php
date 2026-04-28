<?php

namespace App\Filament\Widgets;

use App\Models\CallLog;
use App\Models\Customer;
use App\Models\Order;
use App\Models\StockistTransaction;
use App\Models\TrialOrder;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Session;

class ManagerStatsWidget extends BaseWidget
{
    public static function canView(): bool
    {
        return auth()->user()->role === 'manager' || auth()->user()->role === 'admin';
    }

    protected function getDefaultDateRange(): array
    {
        $now = Carbon::now('Africa/Lagos');
        $workStart = $now->copy()->setHour(8)->setMinute(0)->setSecond(0);
        $workEnd = $now->copy()->setHour(17)->setMinute(0)->setSecond(0);

        if ($now->lt($workStart)) {
            return [
                'from' => $now->copy()->startOfDay(),
                'to' => $now->copy()->startOfDay()->addDay(),
            ];
        }

        if ($now->gte($workEnd)) {
            return [
                'from' => $workStart,
                'to' => $workEnd,
            ];
        }

        return [
            'from' => $workStart,
            'to' => $now,
        ];
    }

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

        return [
            Stat::make('Total Customers', $totalCustomers)
                ->description('New customers')
                ->icon('heroicon-o-users')
                ->color('info'),
            Stat::make('Converted', $convertedCustomers)
                ->description($conversionRate.'% conversion')
                ->icon('heroicon-o-check-circle')
                ->color('success'),
            Stat::make('Trial Orders', $trialOrders)
                ->description('Trial orders')
                ->icon('heroicon-o-beaker')
                ->color('warning'),
            Stat::make('Stockist', $stockTxns)
                ->description('Stockist txns')
                ->icon('heroicon-o-archive-box')
                ->color('danger'),
            Stat::make('Calls Made', $calls)
                ->description('Calls logged')
                ->icon('heroicon-o-phone')
                ->color('primary'),
            Stat::make('Orders', $orders)
                ->description('Orders placed')
                ->icon('heroicon-o-shopping-cart')
                ->color('info'),
            Stat::make('Revenue', '₦'.number_format($revenue, 2))
                ->description('Total revenue')
                ->icon('heroicon-o-banknotes')
                ->color('success'),
        ];
    }
}
