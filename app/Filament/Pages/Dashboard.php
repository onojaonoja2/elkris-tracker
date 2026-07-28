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
        return true;
    }

    public static function canViewNavigation(): bool
    {
        $user = auth()->user();

        return ! $user->hasAnyRole([
            'field_agent',
            'community_sales_representative',
            'open_market',
            'retail_market',
            'sales',
            'supervisor',
            'warehouse_manager',
            'accountant',
        ]);
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

        $role = auth()->user()->getPrimaryRole();

        if ($role === 'supervisor') {
            return redirect()->to(SupervisorDashboard::getUrl([], isAbsolute: false, panel: 'admin'));
        }

        if (in_array($role, ['field_agent', 'community_sales_representative', 'open_market', 'retail_market'])) {
            if ($role === 'community_sales_representative') {
                return redirect()->to(CsrDashboard::getUrl([], isAbsolute: false, panel: 'admin'));
            }

            return redirect()->to(AgentDashboard::getUrl([], isAbsolute: false, panel: 'admin'));
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

        if ($role === 'warehouse_manager') {
            return redirect()->to(WarehouseManagerDashboard::getUrl([], isAbsolute: false, panel: 'admin'));
        }

        if ($role === 'accountant') {
            return redirect()->to(AccountantDashboard::getUrl([], isAbsolute: false, panel: 'admin'));
        }

        if ($role === 'manager' || $role === 'admin') {
            return redirect()->to(ManagerDashboard::getUrl([], isAbsolute: false, panel: 'admin'));
        }
    }

    public function getWidgets(): array
    {
        $role = auth()->user()?->getPrimaryRole() ?? 'guest';

        return match ($role) {
            'field_agent', 'community_sales_representative', 'open_market', 'retail_market' => [
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
