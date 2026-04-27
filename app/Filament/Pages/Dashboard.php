<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Pages\SupervisorDashboard;
use App\Filament\Pages\FieldAgentDashboard;
use App\Filament\Pages\SalesOrdersDashboard;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?int $navigationSort = -2;

    public static function shouldRegisterNavigation(): bool
    {
        // Always register to ensure route exists, control visibility via canViewNavigation
        return true;
    }

    public static function canViewNavigation(): bool
    {
        // Only show in navigation for roles that need it
        return ! in_array(auth()->user()->role, ['field_agent', 'sales', 'supervisor']);
    }

    public static function getNavigationLabel(): string
    {
        return 'Dashboard';
    }

    public function mount()
    {
        if (! auth()->check()) {
            return;
        }

        $role = auth()->user()->role;

        if ($role === 'supervisor') {
            return redirect()->to(SupervisorDashboard::getUrl([], isAbsolute: false, panel: 'admin'));
        }

        if ($role === 'field_agent') {
            return redirect()->to(FieldAgentDashboard::getUrl([], isAbsolute: false, panel: 'admin'));
        }

        if ($role === 'sales') {
            return redirect()->to(SalesOrdersDashboard::getUrl([], isAbsolute: false, panel: 'admin'));
        }
    }

    public function getWidgets(): array
    {
        $role = auth()->user()->role ?? 'guest';

        return match ($role) {
            'field_agent' => [
                \App\Filament\Widgets\UpcomingFollowUps::class,
            ],
            'sales' => [
                \App\Filament\Widgets\OrdersPerCityChart::class,
                \App\Filament\Widgets\UpcomingFollowUps::class,
            ],
            'supervisor' => [
                \App\Filament\Widgets\SupervisorStatsWidget::class ?? \App\Filament\Widgets\ManagerStatsWidget::class,
            ],
            default => [
                \App\Filament\Widgets\ManagerStatsWidget::class,
                \App\Filament\Widgets\UpcomingFollowUps::class,
                \App\Filament\Widgets\OrdersPerCityChart::class,
            ],
        };
    }
}
