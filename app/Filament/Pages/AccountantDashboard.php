<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasDashboardBreakdownModals;
use App\Filament\Pages\Concerns\HasDashboardDateFilter;
use App\Filament\Widgets\AccountantCreditSalesWidget;
use App\Filament\Widgets\AccountantCustomersWidget;
use App\Filament\Widgets\AccountantDamagedReturnsWidget;
use App\Filament\Widgets\AccountantRepSalesWidget;
use App\Filament\Widgets\AccountantSalesRecordsWidget;
use App\Filament\Widgets\AccountantStatsOverviewWidget;
use App\Filament\Widgets\AccountantStockCountApprovalWidget;
use App\Filament\Widgets\AccountantStockLevelsWidget;
use App\Filament\Widgets\AccountantStockMovementsWidget;
use App\Filament\Widgets\AccountantStockReceiveRequestsWidget;
use App\Filament\Widgets\CreditSalesOutstandingStatsWidget;
use App\Filament\Widgets\DamagedReturnsBreakdownWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class AccountantDashboard extends BaseDashboard
{
    use HasDashboardBreakdownModals;
    use HasDashboardDateFilter;

    protected static string $routePath = '/accountant-dashboard';

    protected static ?string $slug = 'accountant-dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = -1;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('accountant');
    }

    public static function canViewNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('accountant');
    }

    public function mount()
    {
        if (! auth()->check() || ! auth()->user()->hasRole('accountant')) {
            return redirect()->to(Dashboard::getUrl([], isAbsolute: false, panel: 'admin'));
        }
    }

    public function getHeaderWidgets(): array
    {
        return [
            AccountantStatsOverviewWidget::class,
            CreditSalesOutstandingStatsWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getDateFilterAction(),
            $this->getClearDateFilterAction(),
            $this->getCreditBreakdownAction(),
        ];
    }

    public function getWidgets(): array
    {
        return [
            AccountantStockReceiveRequestsWidget::class,
            AccountantStockCountApprovalWidget::class,
            AccountantDamagedReturnsWidget::class,
            DamagedReturnsBreakdownWidget::class,
            AccountantCreditSalesWidget::class,
            AccountantCustomersWidget::class,
            AccountantSalesRecordsWidget::class,
            AccountantRepSalesWidget::class,
            AccountantStockLevelsWidget::class,
            AccountantStockMovementsWidget::class,
        ];
    }
}
