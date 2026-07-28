<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AccountantCreditSalesWidget;
use App\Filament\Widgets\AccountantDamagedReturnsWidget;
use App\Filament\Widgets\AccountantRepSalesWidget;
use App\Filament\Widgets\AccountantSalesRecordsWidget;
use App\Filament\Widgets\AccountantStockCountApprovalWidget;
use App\Filament\Widgets\AccountantStockLevelsWidget;
use App\Filament\Widgets\AccountantStockMovementsWidget;
use App\Filament\Widgets\AccountantStockReceiveRequestsWidget;
use App\Filament\Widgets\DamagedReturnsBreakdownWidget;
use App\Filament\Widgets\GeneralAccountantStatsWidget;
use App\Filament\Widgets\ManagerConversionWidget;
use App\Filament\Widgets\ManagerCreditSalesWidget;
use App\Filament\Widgets\ManagerCustomersWidget;
use App\Filament\Widgets\ManagerPortfolioPerAgentWidget;
use App\Filament\Widgets\ManagerStockLevelsOverviewWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class GeneralAccountantDashboard extends BaseDashboard
{
    protected static string $routePath = '/general-accountant-dashboard';

    protected static ?string $slug = 'general-accountant-dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = -1;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('general_accountant');
    }

    public static function canViewNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('general_accountant');
    }

    public function mount()
    {
        if (! auth()->check() || ! auth()->user()->hasRole('general_accountant')) {
            return redirect()->to(Dashboard::getUrl([], isAbsolute: false, panel: 'admin'));
        }
    }

    public function getHeaderWidgets(): array
    {
        return [
            GeneralAccountantStatsWidget::class,
        ];
    }

    public function getWidgets(): array
    {
        return [
            ManagerCustomersWidget::class,
            ManagerPortfolioPerAgentWidget::class,
            ManagerConversionWidget::class,
            AccountantStockReceiveRequestsWidget::class,
            AccountantStockCountApprovalWidget::class,
            AccountantDamagedReturnsWidget::class,
            DamagedReturnsBreakdownWidget::class,
            AccountantCreditSalesWidget::class,
            ManagerCreditSalesWidget::class,
            AccountantSalesRecordsWidget::class,
            AccountantRepSalesWidget::class,
            ManagerStockLevelsOverviewWidget::class,
            AccountantStockLevelsWidget::class,
            AccountantStockMovementsWidget::class,
        ];
    }
}
