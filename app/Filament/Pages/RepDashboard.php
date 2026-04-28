<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\RepPendingAssignmentsWidget;
use App\Filament\Widgets\RepPortfolioWidget;
use App\Filament\Widgets\RepStatsWidget;
use App\Filament\Widgets\UpcomingFollowUps;
use Filament\Pages\Dashboard as BaseDashboard;

class RepDashboard extends BaseDashboard
{
    protected static string $routePath = '/rep-dashboard';

    protected static ?string $slug = 'rep-dashboard';

    protected static ?int $navigationSort = -1;

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public static function canViewNavigation(): bool
    {
        return auth()->user()->role === 'rep';
    }

    public function getHeaderWidgets(): array
    {
        return [
            RepStatsWidget::class,
        ];
    }

    public function getWidgets(): array
    {
        return [
            RepPendingAssignmentsWidget::class,
            RepPortfolioWidget::class,
            UpcomingFollowUps::class,
        ];
    }
}
