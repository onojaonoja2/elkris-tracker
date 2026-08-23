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
use App\Filament\Widgets\AccountantStockTransferApprovalWidget;
use App\Filament\Widgets\CreditSalesOutstandingStatsWidget;
use App\Filament\Widgets\DamagedReturnsBreakdownWidget;
use App\Filament\Widgets\OfficeSalesStatsWidget;
use App\Filament\Widgets\OrderStatsWidget;
use App\Filament\Widgets\ProductionActivityWidget;
use App\Models\SalesRecord;
use App\Support\DashboardDateScope;
use Filament\Actions\Action;
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
            OfficeSalesStatsWidget::class,
            AccountantStatsOverviewWidget::class,
            CreditSalesOutstandingStatsWidget::class,
            OrderStatsWidget::class,
            ProductionActivityWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getDateFilterAction(),
            $this->getClearDateFilterAction(),
            $this->getCreditBreakdownAction(),
            $this->getOfficeSalesBreakdownAction(),
            $this->getRepSalesBreakdownAction(),
            $this->getStockMovementBreakdownAction(),
            $this->getRevenueBreakdownAction(),
            Action::make('exportReport')
                ->label('Export')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(fn () => $this->exportReport())
                ->modalHeading('Export Sales Report'),
        ];
    }

    protected function exportReport()
    {
        [$from, $to] = DashboardDateScope::fromSession();

        $records = SalesRecord::whereBetween('created_at', [$from, $to])
            ->with('agent')
            ->orderBy('created_at', 'asc')
            ->get();

        $filename = 'sales_records_'.date('Y_m_d_H_i_s').'.csv';

        return response()->streamDownload(function () use ($records) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, ['Date', 'Agent', 'Agent Type', 'Products', 'Value', 'Status', 'Payment']);

            foreach ($records as $record) {
                $products = collect($record->products)
                    ->map(fn ($product) => "{$product['quantity']}x {$product['product_name']}")
                    ->implode('; ');

                fputcsv($handle, [
                    $record->created_at->format('d/m/Y H:i'),
                    $record->agent->name ?? 'N/A',
                    $record->agent_type,
                    $products,
                    $record->total_value,
                    $record->status,
                    $record->is_credit ? 'Credit' : 'Cash',
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function getWidgets(): array
    {
        return [
            AccountantStockReceiveRequestsWidget::class,
            AccountantStockTransferApprovalWidget::class,
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
