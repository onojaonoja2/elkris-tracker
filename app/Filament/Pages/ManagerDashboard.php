<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasDashboardBreakdownModals;
use App\Filament\Pages\Concerns\HasDashboardDateFilter;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\AgentCustomerViewWidget;
use App\Filament\Widgets\CreditSalesOutstandingStatsWidget;
use App\Filament\Widgets\DamagedReturnsBreakdownWidget;
use App\Filament\Widgets\ManagerAgentManagementWidget;
use App\Filament\Widgets\ManagerAnalyticsWidget;
use App\Filament\Widgets\ManagerConversionWidget;
use App\Filament\Widgets\ManagerCreditSalesWidget;
use App\Filament\Widgets\ManagerCustomerSubmissionsWidget;
use App\Filament\Widgets\ManagerCustomersWidget;
use App\Filament\Widgets\ManagerPeopleByStateWidget;
use App\Filament\Widgets\ManagerPortfolioPerAgentWidget;
use App\Filament\Widgets\ManagerSalesRecordsByStateWidget;
use App\Filament\Widgets\ManagerStatsWidget;
use App\Filament\Widgets\ManagerStockLevelsOverviewWidget;
use App\Filament\Widgets\ManagerStockMovementsWidget;
use App\Filament\Widgets\OfficeSalesStatsWidget;
use App\Filament\Widgets\OrderStatsWidget;
use App\Filament\Widgets\ProductionActivityWidget;
use App\Filament\Widgets\ProductionOutgoingTransfersWidget;
use App\Filament\Widgets\ProductionRawMaterialsWidget;
use App\Filament\Widgets\ProductionRunsWidget;
use App\Filament\Widgets\ProductionStoreStockWidget;
use App\Filament\Widgets\RevenueTrendChart;
use App\Filament\Widgets\SupervisorCreditSalesWidget;
use App\Filament\Widgets\SupervisorCsrListWidget;
use App\Filament\Widgets\SupervisorDamagedReturnsWidget;
use App\Filament\Widgets\SupervisorDispatchStockWidget;
use App\Filament\Widgets\SupervisorSalesByGeoWidget;
use App\Filament\Widgets\SupervisorSalesRecordsWidget;
use App\Filament\Widgets\SupervisorStockCountApprovalWidget;
use App\Filament\Widgets\SupervisorStockCountFinalApprovalWidget;
use App\Filament\Widgets\SupervisorStockTransferApprovalWidget;
use App\Filament\Widgets\SupervisorStockWidget;
use App\Filament\Widgets\WarehouseReturnApprovalsWidget;
use App\Models\Customer;
use App\Models\Order;
use App\Models\SalesRecord;
use App\Models\User;
use App\Support\DashboardDateScope;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class ManagerDashboard extends BaseDashboard
{
    use HasDashboardBreakdownModals;
    use HasDashboardDateFilter;

    protected static string $routePath = '/manager-dashboard';

    protected static ?string $slug = 'manager-dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = -1;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['manager', 'admin']);
    }

    public static function canViewNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['manager', 'admin']);
    }

    public static function getNavigationLabel(): string
    {
        return 'Dashboard';
    }

    public function mount()
    {
        if (! auth()->check() || ! auth()->user()->hasAnyRole(['manager', 'admin'])) {
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
            ManagerStatsWidget::class,
            ProductionActivityWidget::class,
            CreditSalesOutstandingStatsWidget::class,
            OrderStatsWidget::class,
            ManagerAnalyticsWidget::class,
            ManagerCustomerSubmissionsWidget::class,
        ];
    }

    public function getWidgets(): array
    {
        return [
            ManagerAgentManagementWidget::class,
            ManagerPeopleByStateWidget::class,
            ManagerSalesRecordsByStateWidget::class,
            ManagerCreditSalesWidget::class,
            ManagerStockLevelsOverviewWidget::class,
            ManagerStockMovementsWidget::class,
            ManagerCustomersWidget::class,
            ManagerPortfolioPerAgentWidget::class,
            ManagerConversionWidget::class,
            DamagedReturnsBreakdownWidget::class,
            WarehouseReturnApprovalsWidget::class,
            ProductionRunsWidget::class,
            ProductionRawMaterialsWidget::class,
            ProductionStoreStockWidget::class,
            ProductionOutgoingTransfersWidget::class,
            AgentCustomerViewWidget::class,
            SupervisorCsrListWidget::class,
            SupervisorStockTransferApprovalWidget::class,
            SupervisorStockCountApprovalWidget::class,
            SupervisorStockCountFinalApprovalWidget::class,
            SupervisorSalesByGeoWidget::class,
            SupervisorSalesRecordsWidget::class,
            SupervisorCreditSalesWidget::class,
            SupervisorDamagedReturnsWidget::class,
            SupervisorDispatchStockWidget::class,
            SupervisorStockWidget::class,
            RevenueTrendChart::class,
        ];
    }

    public function getHeaderActions(): array
    {
        return [
            $this->getCreditBreakdownAction(),
            $this->getOrderBreakdownAction(),
            $this->getCsrOrderBreakdownAction(),
            $this->getRevenueBreakdownAction(),
            $this->getOfficeSalesBreakdownAction(),
            $this->getApprovalBreakdownAction(),
            $this->getCustomerBreakdownAction(),
            $this->getCustomersAddedTodayAction(),
            Action::make('addUser')
                ->label('Add User')
                ->icon('heroicon-o-user-plus')
                ->button()
                ->url(UserResource::getUrl('create')),
            $this->getDateFilterAction(),
            $this->getClearDateFilterAction(),
            Action::make('export_report')
                ->label('Export Report')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->action(function () {
                    [$from, $to] = DashboardDateScope::fromSession();

                    $filename = 'system_report_'.Carbon::now()->format('Y_m_d_H_i_s').'.csv';

                    return response()->streamDownload(function () use ($from, $to) {
                        $handle = fopen('php://output', 'w');
                        fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

                        fputcsv($handle, ['Section', 'Metric', 'Value']);

                        $totalCustomers = Customer::whereBetween('created_at', [$from, $to])->count();
                        $totalOrders = Order::whereBetween('created_at', [$from, $to])->where('is_migrated_order', false)->count();
                        $orderRevenue = Order::whereBetween('created_at', [$from, $to])->where('is_migrated_order', false)->sum('total_price');
                        $salesRecords = SalesRecord::whereBetween('created_at', [$from, $to])->count();
                        $pendingSales = SalesRecord::whereIn('status', ['pending', 'receipt_uploaded'])->count();
                        $activeAgents = User::whereIn('role', ['field_agent', 'community_sales_representative', 'open_market', 'retail_market'])->active()->count();

                        fputcsv($handle, ['Sales', 'Total Customers', $totalCustomers]);
                        fputcsv($handle, ['Sales', 'Total Orders', $totalOrders]);
                        fputcsv($handle, ['Sales', 'Order Revenue (₦)', number_format($orderRevenue, 2)]);
                        fputcsv($handle, ['Sales', 'Sales Records', $salesRecords]);
                        fputcsv($handle, ['Sales', 'Pending Approvals', $pendingSales]);
                        fputcsv($handle, ['Sales', 'Active Agents', $activeAgents]);

                        $roleCounts = User::select('role', DB::raw('COUNT(*) as count'))
                            ->where('is_active', true)
                            ->groupBy('role')
                            ->pluck('count', 'role');

                        fputcsv($handle, ['Users', 'Total Active Users', $roleCounts->sum()]);
                        foreach ($roleCounts as $role => $count) {
                            fputcsv($handle, ['Users', str_replace('_', ' ', $role), $count]);
                        }

                        fclose($handle);
                    }, $filename, ['Content-Type' => 'text/csv']);
                }),
        ];
    }
}
