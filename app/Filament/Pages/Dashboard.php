<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ManagerStatsWidget;
use App\Filament\Widgets\OrdersPerCityChart;
use App\Filament\Widgets\UpcomingFollowUps;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\Route;

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

        if ($role === 'lead') {
            $panel = Filament::getPanel('admin');
            $routeName = LeadDashboard::getRouteName($panel);

            if (Route::has($routeName)) {
                return redirect()->to(LeadDashboard::getUrl([], isAbsolute: false, panel: 'admin'));
            }

            return redirect()->to(url($panel->getPath().'/lead-dashboard'));
        }

        if ($role === 'sales') {
            return redirect()->to(SalesOrdersDashboard::getUrl([], isAbsolute: false, panel: 'admin'));
        }

        if ($role === 'rep') {
            return redirect()->to(RepDashboard::getUrl([], isAbsolute: false, panel: 'admin'));
        }

        if ($role === 'manager' || $role === 'admin') {
            return redirect()->to(ManagerDashboard::getUrl([], isAbsolute: false, panel: 'admin'));
        }
    }

    public function getWidgets(): array
    {
        $role = auth()->user()->role ?? 'guest';

        return match ($role) {
            'field_agent' => [
                UpcomingFollowUps::class,
            ],
            'lead' => [
                UpcomingFollowUps::class,
            ],
            'sales' => [
                OrdersPerCityChart::class,
                UpcomingFollowUps::class,
            ],
            default => [
                ManagerStatsWidget::class,
                UpcomingFollowUps::class,
                OrdersPerCityChart::class,
            ],
        };
    }
}
