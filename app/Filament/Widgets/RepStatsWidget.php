<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RepStatsWidget extends StatsOverviewWidget
{
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
        ];
    }

    public static function canView(): bool
    {
        return auth()->user()->role === 'rep';
    }
}
