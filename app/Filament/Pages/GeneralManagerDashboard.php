<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasDashboardBreakdownModals;
use App\Filament\Pages\Concerns\HasDashboardDateFilter;
use App\Filament\Widgets\AgentCustomerViewWidget;
use App\Filament\Widgets\CreditSalesOutstandingStatsWidget;
use App\Filament\Widgets\DamagedReturnsBreakdownWidget;
use App\Filament\Widgets\GeneralManagerStatsWidget;
use App\Filament\Widgets\ManagerAnalyticsWidget;
use App\Filament\Widgets\ManagerConversionWidget;
use App\Filament\Widgets\ManagerCreditSalesWidget;
use App\Filament\Widgets\ManagerCustomersWidget;
use App\Filament\Widgets\ManagerPeopleByStateWidget;
use App\Filament\Widgets\ManagerPortfolioPerAgentWidget;
use App\Filament\Widgets\ManagerSalesRecordsByStateWidget;
use App\Filament\Widgets\ManagerStockLevelsOverviewWidget;
use App\Filament\Widgets\ManagerStockMovementsWidget;
use App\Filament\Widgets\OfficeSalesStatsWidget;
use App\Filament\Widgets\OrdersPerCityChart;
use App\Filament\Widgets\OrderStatsWidget;
use App\Filament\Widgets\ProductionActivityWidget;
use App\Filament\Widgets\ProductionOutgoingTransfersWidget;
use App\Filament\Widgets\ProductionRawMaterialsWidget;
use App\Filament\Widgets\ProductionRunsWidget;
use App\Filament\Widgets\ProductionStoreStockWidget;
use App\Filament\Widgets\RevenueTrendChart;
use App\Filament\Widgets\WarehouseReturnApprovalsWidget;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\Session;

class GeneralManagerDashboard extends BaseDashboard
{
    use HasDashboardBreakdownModals;
    use HasDashboardDateFilter;

    protected static string $routePath = '/general-manager-dashboard';

    protected static ?string $slug = 'general-manager-dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = -1;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('general_manager');
    }

    public static function canViewNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('general_manager');
    }

    public function mount()
    {
        if (! auth()->check() || ! auth()->user()->hasRole('general_manager')) {
            return redirect()->to(Dashboard::getUrl([], isAbsolute: false, panel: 'admin'));
        }

        if (! Session::has('dashboard_date_from')) {
            Session::put('dashboard_date_from', now()->startOfDay()->toDateTimeString());
            Session::put('dashboard_date_to', now()->endOfDay()->toDateTimeString());
        }
    }

    public function getHeaderWidgets(): array
    {
        return [
            OfficeSalesStatsWidget::class,
            GeneralManagerStatsWidget::class,
            ManagerAnalyticsWidget::class,
            ProductionActivityWidget::class,
            CreditSalesOutstandingStatsWidget::class,
            OrderStatsWidget::class,
        ];
    }

    public function getWidgets(): array
    {
        return [
            ManagerPeopleByStateWidget::class,
            ManagerSalesRecordsByStateWidget::class,
            ManagerCreditSalesWidget::class,
            ManagerStockLevelsOverviewWidget::class,
            ManagerStockMovementsWidget::class,
            ManagerCustomersWidget::class,
            ManagerPortfolioPerAgentWidget::class,
            ManagerConversionWidget::class,
            AgentCustomerViewWidget::class,
            DamagedReturnsBreakdownWidget::class,
            WarehouseReturnApprovalsWidget::class,
            ProductionRunsWidget::class,
            ProductionRawMaterialsWidget::class,
            ProductionStoreStockWidget::class,
            ProductionOutgoingTransfersWidget::class,
            RevenueTrendChart::class,
            OrdersPerCityChart::class,
        ];
    }

    public function getHeaderActions(): array
    {
        return [
            $this->getCreditBreakdownAction(),
            $this->getOrderBreakdownAction(),
            $this->getOfficeSalesBreakdownAction(),
            $this->getCustomerBreakdownAction(),
            $this->getCustomersAddedTodayAction(),
            $this->getDateFilterAction(),
            $this->getClearDateFilterAction(),
        ];
    }
}
