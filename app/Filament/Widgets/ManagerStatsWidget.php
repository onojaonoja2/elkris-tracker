<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
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
        $totalOrdersCount = Customer::where('total_price', '>', 0)->count();
        $totalQuantitySum = (int) Customer::sum('order_quantity');

        $agentSubmissions = Customer::whereNotNull('agent_id')->count();
        $agentConversions = Customer::whereNotNull('agent_id')->where('total_price', '>', 0)->count();

        $conversionRate = $agentSubmissions > 0 
            ? round(($agentConversions / $agentSubmissions) * 100, 2) 
            : 0;

        return [
            Stat::make('Total Orders Placed', $totalOrdersCount)
                ->description('Total number of successful orders')
                ->descriptionIcon('heroicon-m-shopping-cart'),
            Stat::make('Gross Quantity Ordered', $totalQuantitySum)
                ->description('Total units across all orders'),
            Stat::make('Field Agent Conversion', $conversionRate . '%')
                ->description($agentConversions . ' ordered out of ' . $agentSubmissions . ' submitted')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
        ];
    }
}
