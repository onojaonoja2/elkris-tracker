<?php

namespace App\Filament\Widgets;

use App\Models\Stockist;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SupervisorStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $user = auth()->user();
        $stockists = Stockist::where('supervisor_id', $user->id)->get();

        $stockistCount = $stockists->count();

        $stockistCities = $stockists->pluck('city')->toArray();
        $fieldAgentCount = User::where('role', 'field_agent')
            ->where(function ($query) use ($stockistCities) {
                foreach ($stockistCities as $city) {
                    $query->orWhereJsonContains('assigned_cities', $city);
                }
            })
            ->count();

        return [
            Stat::make('Stockists', $stockistCount)
                ->description('Registered stockists')
                ->icon('heroicon-o-building-storefront')
                ->color('info'),
            Stat::make('Field Agents', $fieldAgentCount)
                ->description('Active field agents')
                ->icon('heroicon-o-users')
                ->color('warning'),
        ];
    }
}
