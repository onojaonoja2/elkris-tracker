<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ManagerAnalyticsWidget;
use App\Filament\Widgets\ManagerConversionWidget;
use App\Filament\Widgets\ManagerPortfolioPerAgentWidget;
use App\Filament\Widgets\ManagerStatsWidget;
use App\Filament\Widgets\OrdersPerCityChart;
use App\Filament\Widgets\UpcomingFollowUps;
use Filament\Pages\Dashboard as BaseDashboard;

class ManagerDashboard extends BaseDashboard
{
    protected static string $routePath = '/manager-dashboard';

    protected static ?string $slug = 'manager-dashboard';

    protected static ?int $navigationSort = -1;

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public static function canViewNavigation(): bool
    {
        return auth()->user()->role === 'manager' || auth()->user()->role === 'admin';
    }

    public function getHeaderWidgets(): array
    {
        return [
            ManagerStatsWidget::class,
        ];
    }

    public function getWidgets(): array
    {
        return [
            ManagerAnalyticsWidget::class,
            ManagerPortfolioPerAgentWidget::class,
            ManagerConversionWidget::class,
            OrdersPerCityChart::class,
            UpcomingFollowUps::class,
        ];
    }
}
