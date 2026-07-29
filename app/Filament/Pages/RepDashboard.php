<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\CsrOverviewWidget;
use App\Filament\Widgets\RepOrderAssignmentWidget;
use App\Filament\Widgets\RepPendingAssignmentsWidget;
use App\Filament\Widgets\RepPortfolioWidget;
use App\Filament\Widgets\RepStatsWidget;
use App\Filament\Widgets\UpcomingFollowUps;
use Filament\Pages\Dashboard as BaseDashboard;

class RepDashboard extends BaseDashboard
{
    protected static string $routePath = '/rep-dashboard';

    protected static ?string $slug = 'rep-dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = -1;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('rep');
    }

    public static function canViewNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('rep');
    }

    public static function getNavigationLabel(): string
    {
        return 'Dashboard';
    }

    public function mount()
    {
        if (! auth()->check() || ! auth()->user()->hasRole('rep')) {
            return redirect()->to(Dashboard::getUrl([], isAbsolute: false, panel: 'admin'));
        }
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
            RepOrderAssignmentWidget::class,
            RepPendingAssignmentsWidget::class,
            RepPortfolioWidget::class,
            CsrOverviewWidget::class,
            UpcomingFollowUps::class,
        ];
    }
}
