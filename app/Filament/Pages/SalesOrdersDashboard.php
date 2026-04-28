<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\OrdersPerCityChart;
use Filament\Pages\Dashboard as BaseDashboard;

class SalesOrdersDashboard extends BaseDashboard
{
    protected static string $routePath = '/sales-orders-dashboard';

    protected static ?string $slug = 'sales-orders-dashboard';

    protected static bool $shouldRegisterNavigation = false;

    public function getWidgets(): array
    {
        return [
            OrdersPerCityChart::class,
        ];
    }
}
