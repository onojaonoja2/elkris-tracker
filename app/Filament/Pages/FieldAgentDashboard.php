<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\UpcomingFollowUps;
use Filament\Pages\Dashboard as BaseDashboard;

class FieldAgentDashboard extends BaseDashboard
{
    protected static string $routePath = '/field-agent-dashboard';

    protected static ?string $slug = 'field-agent-dashboard';

    protected static ?int $navigationSort = -1;

    protected static bool $shouldRegisterNavigation = false;

    public function getWidgets(): array
    {
        return [
            UpcomingFollowUps::class,
        ];
    }
}
