<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ProductionActivityWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class ProductionDashboard extends BaseDashboard
{
    protected static string $routePath = '/production-dashboard';

    protected static ?string $slug = 'production-dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = -1;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('production_management');
    }

    public static function canViewNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('production_management');
    }

    public function mount()
    {
        if (! auth()->check() || ! auth()->user()->hasRole('production_management')) {
            return redirect()->to(Dashboard::getUrl([], isAbsolute: false, panel: 'admin'));
        }
    }

    public function getHeaderWidgets(): array
    {
        return [
            ProductionActivityWidget::class,
        ];
    }
}
