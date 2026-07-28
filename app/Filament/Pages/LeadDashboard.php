<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\CsrOverviewWidget;
use App\Filament\Widgets\LeadAgentSubmissionsWidget;
use App\Filament\Widgets\LeadCsrAssignmentWidget;
use App\Filament\Widgets\LeadCsrSubmissionsWidget;
use App\Filament\Widgets\LeadOrdersStatsWidget;
use App\Filament\Widgets\LeadOrdersWidget;
use App\Filament\Widgets\LeadPendingAssignmentsWidget;
use App\Filament\Widgets\LeadPersonalPortfolioWidget;
use App\Filament\Widgets\LeadPortfolioWidget;
use App\Filament\Widgets\LeadRejectedCustomersWidget;
use App\Filament\Widgets\LeadStatsWidget;
use App\Filament\Widgets\UpcomingFollowUps;
use Filament\Pages\Dashboard as BaseDashboard;

class LeadDashboard extends BaseDashboard
{
    protected static string $routePath = '/lead-dashboard';

    protected static ?string $slug = 'lead-dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = -1;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('lead');
    }

    public static function canViewNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('lead');
    }

    public static function getNavigationLabel(): string
    {
        return 'Dashboard';
    }

    public function mount()
    {
        if (! auth()->check() || ! auth()->user()->hasRole('lead')) {
            return redirect()->to(Dashboard::getUrl([], isAbsolute: false, panel: 'admin'));
        }
    }

    public function getHeaderWidgets(): array
    {
        return [
            LeadStatsWidget::class,
            LeadOrdersStatsWidget::class,
            LeadAgentSubmissionsWidget::class,
            LeadCsrSubmissionsWidget::class,
            LeadPendingAssignmentsWidget::class,
            LeadRejectedCustomersWidget::class,
            LeadPortfolioWidget::class,
            LeadOrdersWidget::class,
        ];
    }

    public function getWidgets(): array
    {
        return [
            LeadCsrAssignmentWidget::class,
            LeadPersonalPortfolioWidget::class,
            CsrOverviewWidget::class,
            UpcomingFollowUps::class,
        ];
    }
}
