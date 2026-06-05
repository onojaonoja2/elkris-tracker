<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AccountantSalesRecordsWidget;
use App\Filament\Widgets\AccountantStockMovementsWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class AccountantDashboard extends BaseDashboard
{
    protected static string $routePath = '/accountant-dashboard';

    protected static ?string $slug = 'accountant-dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = -1;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->role === 'accountant';
    }

    public static function canViewNavigation(): bool
    {
        return auth()->check() && auth()->user()->role === 'accountant';
    }

    public function mount()
    {
        if (! auth()->check() || auth()->user()->role !== 'accountant') {
            return redirect()->to(Dashboard::getUrl([], isAbsolute: false, panel: 'admin'));
        }
    }

    public function getWidgets(): array
    {
        return [
            AccountantSalesRecordsWidget::class,
            AccountantStockMovementsWidget::class,
        ];
    }
}
