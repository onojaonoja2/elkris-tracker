<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasDashboardBreakdownModals;
use App\Filament\Pages\Concerns\HasDashboardDateFilter;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\AgentCustomerViewWidget;
use App\Filament\Widgets\CreditSalesOutstandingStatsWidget;
use App\Filament\Widgets\DamagedReturnsBreakdownWidget;
use App\Filament\Widgets\OrderStatsWidget;
use App\Filament\Widgets\SupervisorCreditSalesWidget;
use App\Filament\Widgets\SupervisorCsrListWidget;
use App\Filament\Widgets\SupervisorDamagedReturnsWidget;
use App\Filament\Widgets\SupervisorDispatchStockWidget;
use App\Filament\Widgets\SupervisorSalesByGeoWidget;
use App\Filament\Widgets\SupervisorSalesRecordsWidget;
use App\Filament\Widgets\SupervisorStatsWidget;
use App\Filament\Widgets\SupervisorStockCountApprovalWidget;
use App\Filament\Widgets\SupervisorStockCountFinalApprovalWidget;
use App\Filament\Widgets\SupervisorStockTransferApprovalWidget;
use App\Filament\Widgets\SupervisorStockWidget;
use App\Models\SalesRecord;
use App\Models\User;
use App\Support\DashboardDateScope;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\On;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupervisorDashboard extends BaseDashboard
{
    use HasDashboardBreakdownModals;
    use HasDashboardDateFilter;

    protected static string $routePath = '/supervisor-dashboard';

    protected static ?string $slug = 'supervisor-dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = -1;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('supervisor');
    }

    public static function canViewNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('supervisor');
    }

    public static function getNavigationLabel(): string
    {
        return 'Dashboard';
    }

    public function mount()
    {
        if (! auth()->check() || ! auth()->user()->hasRole('supervisor')) {
            return redirect()->to(Dashboard::getUrl([], isAbsolute: false, panel: 'admin'));
        }

        if (! Session::has('dashboard_date_from')) {
            Session::put('dashboard_date_from', now()->startOfDay()->toDateTimeString());
            Session::put('dashboard_date_to', now()->endOfDay()->toDateTimeString());
        }
    }

    protected function getHeaderWidgets(): array
    {
        return [
            SupervisorStatsWidget::class,
            CreditSalesOutstandingStatsWidget::class,
            OrderStatsWidget::class,
            SupervisorStockWidget::class,
        ];
    }

    public function getWidgets(): array
    {
        return [
            SupervisorCsrListWidget::class,
            SupervisorStockTransferApprovalWidget::class,
            SupervisorStockCountApprovalWidget::class,
            SupervisorStockCountFinalApprovalWidget::class,
            SupervisorSalesByGeoWidget::class,
            SupervisorSalesRecordsWidget::class,
            SupervisorCreditSalesWidget::class,
            SupervisorDamagedReturnsWidget::class,
            SupervisorDispatchStockWidget::class,
            DamagedReturnsBreakdownWidget::class,
            AgentCustomerViewWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getCreditBreakdownAction(),
            $this->getOrderBreakdownAction(),
            $this->getApprovalBreakdownAction(),
            $this->getRevenueBreakdownAction(),

            Action::make('addCsr')
                ->label('Add CSR')
                ->icon('heroicon-o-user-plus')
                ->button()
                ->url(UserResource::getUrl('create')),

            $this->getDateFilterAction(),

            $this->getClearDateFilterAction(),

            Action::make('exportReport')
                ->label('Export')
                ->icon('heroicon-o-arrow-down-tray')
                ->button()
                ->action(fn () => $this->exportReport())
                ->modalHeading('Export Sales Report'),
        ];
    }

    #[On('open-period-sales-export')]
    public function exportPeriodSales(): StreamedResponse
    {
        return $this->exportReport();
    }

    protected function exportReport()
    {
        [$from, $to] = DashboardDateScope::fromSession();

        $csrIds = User::where('role', 'community_sales_representative')->pluck('id');

        $records = SalesRecord::whereIn('agent_id', $csrIds)
            ->whereBetween('created_at', [$from, $to])
            ->with('agent')
            ->orderBy('created_at', 'asc')
            ->get();

        $filename = 'csr_sales_report_'.date('Y_m_d_H_i_s').'.csv';

        return response()->streamDownload(function () use ($records) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, ['Date', 'Agent', 'Products', 'Value', 'Status']);

            foreach ($records as $r) {
                $products = collect($r->products)->map(fn ($p) => "{$p['quantity']}x {$p['product_name']}")->implode('; ');
                fputcsv($handle, [
                    $r->created_at->format('d/m/Y H:i'),
                    $r->agent->name ?? 'N/A',
                    $products,
                    $r->total_value,
                    $r->status,
                ]);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
