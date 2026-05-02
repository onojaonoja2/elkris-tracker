<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\FieldAgentDailySubmissionsWidget;
use App\Filament\Widgets\FieldAgentReplaceCustomersWidget;
use App\Filament\Widgets\UpcomingFollowUps;
use Filament\Pages\Dashboard as BaseDashboard;

class FieldAgentDashboard extends BaseDashboard
{
    protected static string $routePath = '/field-agent-dashboard';

    protected static ?string $slug = 'field-agent-dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = -1;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->role === 'field_agent';
    }

    public static function canViewNavigation(): bool
    {
        return auth()->check() && auth()->user()->role === 'field_agent';
    }

    public static function getNavigationLabel(): string
    {
        return 'Dashboard';
    }

    public function getHeaderWidgets(): array
    {
        return [
            FieldAgentDailySubmissionsWidget::class,
        ];
    }

    public function getWidgets(): array
    {
        return [
            FieldAgentReplaceCustomersWidget::class,
            UpcomingFollowUps::class,
        ];
    }
}
