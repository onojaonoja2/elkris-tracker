<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\SalesAssignedOrdersWidget;
use App\Filament\Widgets\SalesCsrOverviewWidget;
use App\Filament\Widgets\SalesDamagedReturnWidget;
use App\Filament\Widgets\SalesInventoryStatsWidget;
use App\Filament\Widgets\SalesPendingOrdersWidget;
use App\Filament\Widgets\SalesStockRequestWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class SalesOrdersDashboard extends BaseDashboard
{
    protected static string $routePath = '/sales-orders-dashboard';

    protected static ?string $slug = 'sales-orders-dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = -1;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->role === 'sales';
    }

    public static function canViewNavigation(): bool
    {
        return auth()->check() && auth()->user()->role === 'sales';
    }

    public static function getNavigationLabel(): string
    {
        return 'Dashboard';
    }

    public function mount()
    {
        if (! auth()->check() || auth()->user()->role !== 'sales') {
            return redirect()->to(Dashboard::getUrl([], isAbsolute: false, panel: 'admin'));
        }
    }

    public function getHeaderWidgets(): array
    {
        return [
            SalesInventoryStatsWidget::class,
        ];
    }

    public function getWidgets(): array
    {
        return [
            SalesStockRequestWidget::class,
            SalesPendingOrdersWidget::class,
            SalesAssignedOrdersWidget::class,
            SalesCsrOverviewWidget::class,
            SalesDamagedReturnWidget::class,
        ];
    }
}
