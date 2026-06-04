<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use App\Models\Order;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class RepStatsWidget extends StatsOverviewWidget
{
    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    protected function getStats(): array
    {
        $repId = auth()->id();

        $pendingCount = Customer::where('rep_id', $repId)
            ->where('rep_acceptance_status', 'pending')
            ->count();

        $portfolioCount = Customer::where('rep_id', $repId)
            ->where('rep_acceptance_status', 'accepted')
            ->count();

        $convertedCount = Customer::where('rep_id', $repId)
            ->where('rep_acceptance_status', 'accepted')
            ->whereHas('orders')
            ->count();

        $conversionRate = $portfolioCount > 0 ? round(($convertedCount / $portfolioCount) * 100, 1) : 0;

        $ordersTodayQuery = Order::where('user_id', $repId)
            ->whereDate('created_at', Carbon::today());

        $ordersToday = $ordersTodayQuery->count();
        $ordersTodayValue = number_format($ordersTodayQuery->sum('total_price'), 2);

        return [
            Stat::make('Pending Assignments', $pendingCount)
                ->description('Awaiting your acceptance')
                ->icon('heroicon-o-clock')
                ->color('warning'),
            Stat::make('Total Portfolio', $portfolioCount)
                ->description('Customers in portfolio')
                ->icon('heroicon-o-users')
                ->color('info'),
            Stat::make('Converted', $convertedCount)
                ->description($conversionRate.'% conversion rate')
                ->icon('heroicon-o-check-circle')
                ->color('success'),
            Stat::make('Orders Today', $ordersToday)
                ->description('₦'.$ordersTodayValue.' total value')
                ->icon('heroicon-o-shopping-cart')
                ->color('primary'),
        ];
    }

    public static function canView(): bool
    {
        return auth()->user()->role === 'rep';
    }
}
