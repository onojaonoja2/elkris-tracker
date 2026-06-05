<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\StockistPendingDispatchesWidget;
use App\Filament\Widgets\StockistStatsWidget;
use App\Filament\Widgets\StockistStocksWidget;
use App\Filament\Widgets\UpcomingFollowUps;
use Filament\Pages\Dashboard as BaseDashboard;

class StockistDashboard extends BaseDashboard
{
    protected static string $routePath = '/stockist-dashboard';

    protected static ?string $slug = 'stockist-dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = -1;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->role === 'stockist';
    }

    public static function canViewNavigation(): bool
    {
        return auth()->check() && auth()->user()->role === 'stockist';
    }

    public static function getNavigationLabel(): string
    {
        return 'Dashboard';
    }

    public function mount()
    {
        if (! auth()->check() || auth()->user()->role !== 'stockist') {
            return redirect()->to(Dashboard::getUrl([], isAbsolute: false, panel: 'admin'));
        }
    }

    public function getHeaderWidgets(): array
    {
        return [
            StockistStatsWidget::class,
        ];
    }

    public function getWidgets(): array
    {
        return [
            StockistPendingDispatchesWidget::class,
            StockistStocksWidget::class,
            UpcomingFollowUps::class,
        ];
    }
}
