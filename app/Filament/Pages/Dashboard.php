<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Widgets\ManagerStatsWidget;
use App\Filament\Widgets\OrdersPerCityChart;
use App\Filament\Widgets\SupervisorStatsWidget;
use App\Filament\Widgets\UpcomingFollowUps;
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
            return redirect()->to(ListCustomers::getUrl([], isAbsolute: false, panel: 'admin'));
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
                UpcomingFollowUps::class,
            ],
            'sales' => [
                OrdersPerCityChart::class,
                UpcomingFollowUps::class,
            ],
            'supervisor' => [
                SupervisorStatsWidget::class ?? ManagerStatsWidget::class,
            ],
            default => [
                ManagerStatsWidget::class,
                UpcomingFollowUps::class,
                OrdersPerCityChart::class,
            ],
        };
    }
}
