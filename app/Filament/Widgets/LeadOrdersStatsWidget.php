<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class LeadOrdersStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $leadId = auth()->id();
        $repIds = User::where('lead_id', $leadId)->where('role', 'rep')->pluck('id')->toArray();
        $allUserIds = array_merge([$leadId], $repIds);

        $leadTotal = Order::where('user_id', $leadId)->where('status', 'delivered')->sum('total_price');
        $repTotal = Order::whereIn('user_id', $repIds)->where('status', 'delivered')->sum('total_price');
        $teamTotal = $leadTotal + $repTotal;

        $leadOrders = Order::where('user_id', $leadId)->count();
        $repOrders = Order::whereIn('user_id', $repIds)->count();
        $teamOrders = $leadOrders + $repOrders;

        return [
            Stat::make('My Orders', $leadOrders)
                ->description('₦'.number_format($leadTotal, 2))
                ->icon('heroicon-o-shopping-bag')
                ->color('info'),
            Stat::make('Rep Orders', $repOrders)
                ->description('₦'.number_format($repTotal, 2))
                ->icon('heroicon-o-user-group')
                ->color('primary'),
            Stat::make('Team Orders', $teamOrders)
                ->description('₦'.number_format($teamTotal, 2))
                ->icon('heroicon-o-building-office')
                ->color('success'),
        ];
    }

    public static function canView(): bool
    {
        return auth()->user()->role === 'lead';
    }
}
