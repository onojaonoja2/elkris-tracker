<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ManagerStatsWidget extends BaseWidget
{
    public static function canView(): bool
    {
        return auth()->user()->role === 'manager';
    }

    protected function getStats(): array
    {
        $totalOrdersCount = Order::where('status', '!=', 'cancelled')->count();
        $totalRevenue = (float) Order::where('status', '!=', 'cancelled')->sum('total_price');

        $agentSubmissions = Customer::whereNotNull('agent_id')->count();
        $agentConversions = Customer::whereNotNull('agent_id')
            ->whereHas('orders', fn ($q) => $q->where('status', '!=', 'cancelled'))
            ->count();

        $conversionRate = $agentSubmissions > 0
            ? round(($agentConversions / $agentSubmissions) * 100, 2)
            : 0;

        return [
            Stat::make('Total Orders Placed', $totalOrdersCount)
                ->description('Total number of successful orders')
                ->descriptionIcon('heroicon-m-shopping-cart'),
            Stat::make('Total Revenue', '₦'.number_format($totalRevenue, 2))
                ->description('Gross revenue from all orders'),
            Stat::make('Field Agent Conversion', $conversionRate.'%')
                ->description($agentConversions.' ordered out of '.$agentSubmissions.' submitted')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
        ];
    }
}
