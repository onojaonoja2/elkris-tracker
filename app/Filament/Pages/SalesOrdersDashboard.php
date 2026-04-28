<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\OrdersPerCityChart;
use Filament\Pages\Dashboard as BaseDashboard;

class SalesOrdersDashboard extends BaseDashboard
{
    protected static string $routePath = '/sales-orders-dashboard';

    protected static ?string $slug = 'sales-orders-dashboard';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->role === 'sales';
    }

    public function mount()
    {
        if (! auth()->check() || auth()->user()->role !== 'sales') {
            return redirect()->to(Dashboard::getUrl([], isAbsolute: false, panel: 'admin'));
        }
    }

    public function getWidgets(): array
    {
        return [
            OrdersPerCityChart::class,
        ];
    }
}
