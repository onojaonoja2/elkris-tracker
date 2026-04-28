<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\CreatedPerRepChart;
use App\Filament\Widgets\LeadAgentSubmissionsWidget;
use App\Filament\Widgets\LeadOrdersStatsWidget;
use App\Filament\Widgets\LeadOrdersWidget;
use App\Filament\Widgets\LeadPendingAssignmentsWidget;
use App\Filament\Widgets\LeadPortfolioWidget;
use App\Filament\Widgets\LeadStatsWidget;
use App\Filament\Widgets\UpcomingFollowUps;
use Filament\Pages\Dashboard as BaseDashboard;

class LeadDashboard extends BaseDashboard
{
    protected static string $routePath = '/lead-dashboard';

    protected static ?string $slug = 'lead-dashboard';

    protected static ?int $navigationSort = -1;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->role === 'lead';
    }

    public static function canViewNavigation(): bool
    {
        return auth()->user()->role === 'lead';
    }

    public function mount()
    {
        if (! auth()->check() || auth()->user()->role !== 'lead') {
            return redirect()->to(Dashboard::getUrl([], isAbsolute: false, panel: 'admin'));
        }
    }

    public function getHeaderWidgets(): array
    {
        return [
            LeadAgentSubmissionsWidget::class,
            LeadPendingAssignmentsWidget::class,
            LeadPortfolioWidget::class,
            LeadOrdersWidget::class,
        ];
    }

    public function getWidgets(): array
    {
        return [
            LeadStatsWidget::class,
            LeadOrdersStatsWidget::class,
            CreatedPerRepChart::class,
            UpcomingFollowUps::class,
        ];
    }
}
