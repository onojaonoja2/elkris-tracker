<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\SalesRecord;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CsrDailySalesWidget extends BaseWidget
{
    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $user = auth()->user();
        $today = Carbon::today();
        $thisWeekStart = Carbon::now()->startOfWeek();
        $thisWeekEnd = Carbon::now()->endOfWeek();

        $orderValueToday = Order::where('user_id', $user->id)
            ->whereDate('created_at', $today)
            ->sum('total_price');

        $salesValueToday = SalesRecord::revenue([$user->id], $today, Carbon::now());

        $orderValueWeek = Order::where('user_id', $user->id)
            ->whereBetween('created_at', [$thisWeekStart, $thisWeekEnd])
            ->sum('total_price');

        $salesValueWeek = SalesRecord::revenue([$user->id], $thisWeekStart, $thisWeekEnd);

        return [
            Stat::make('Today\'s Order Value', '₦'.number_format($orderValueToday, 2))
                ->description('Orders submitted today')
                ->color('success'),
            Stat::make('Today\'s Sales Value', '₦'.number_format($salesValueToday, 2))
                ->description('Sales recorded today')
                ->color('primary'),
            Stat::make('This Week\'s Total', '₦'.number_format($orderValueWeek + $salesValueWeek, 2))
                ->description('Combined order & sales value this week')
                ->color('warning'),
        ];
    }
}
