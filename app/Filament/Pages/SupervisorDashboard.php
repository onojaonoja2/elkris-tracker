<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\AgentCustomerViewWidget;
use App\Filament\Widgets\DamagedReturnsBreakdownWidget;
use App\Filament\Widgets\SupervisorCreditSalesWidget;
use App\Filament\Widgets\SupervisorCsrListWidget;
use App\Filament\Widgets\SupervisorDamagedReturnsWidget;
use App\Filament\Widgets\SupervisorSalesByGeoWidget;
use App\Filament\Widgets\SupervisorSalesRecordsWidget;
use App\Filament\Widgets\SupervisorStatsWidget;
use App\Filament\Widgets\SupervisorStockCountApprovalWidget;
use App\Filament\Widgets\SupervisorStockTransferApprovalWidget;
use App\Filament\Widgets\SupervisorStockWidget;
use App\Models\SalesRecord;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\Session;

class SupervisorDashboard extends BaseDashboard
{
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

        if (! Session::has('supervisor_date_from')) {
            Session::put('supervisor_date_from', now()->startOfDay()->toDateTimeString());
            Session::put('supervisor_date_to', now()->endOfDay()->toDateTimeString());
        }
    }

    protected function getHeaderWidgets(): array
    {
        return [
            SupervisorStatsWidget::class,
            SupervisorStockWidget::class,
        ];
    }

    public function getWidgets(): array
    {
        return [
            SupervisorCsrListWidget::class,
            SupervisorStockTransferApprovalWidget::class,
            SupervisorStockCountApprovalWidget::class,
            SupervisorSalesByGeoWidget::class,
            SupervisorSalesRecordsWidget::class,
            SupervisorCreditSalesWidget::class,
            SupervisorDamagedReturnsWidget::class,
            DamagedReturnsBreakdownWidget::class,
            AgentCustomerViewWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addCsr')
                ->label('Add CSR')
                ->icon('heroicon-o-user-plus')
                ->button()
                ->url(UserResource::getUrl('create')),

            Action::make('filterDates')
                ->label('Filter')
                ->icon('heroicon-o-funnel')
                ->button()
                ->form([
                    DatePicker::make('date_from')
                        ->label('From')
                        ->default(fn () => Session::get('supervisor_date_from', now()->startOfDay()))
                        ->required(),
                    DatePicker::make('date_to')
                        ->label('To')
                        ->default(fn () => Session::get('supervisor_date_to', now()->endOfDay()))
                        ->required(),
                ])
                ->action(function (array $data) {
                    Session::put('supervisor_date_from', $data['date_from']);
                    Session::put('supervisor_date_to', $data['date_to']);
                    $this->dispatch('refresh-dashboard');
                    Notification::make()->title('Filter applied')->success()->send();
                })
                ->modalHeading('Filter by Date Range'),

            Action::make('clearFilter')
                ->label('Today')
                ->icon('heroicon-o-x-mark')
                ->color('gray')
                ->action(function () {
                    Session::put('supervisor_date_from', now()->startOfDay()->toDateTimeString());
                    Session::put('supervisor_date_to', now()->endOfDay()->toDateTimeString());
                    $this->dispatch('refresh-dashboard');
                }),

            Action::make('exportReport')
                ->label('Export')
                ->icon('heroicon-o-arrow-down-tray')
                ->button()
                ->action(fn () => $this->exportReport())
                ->modalHeading('Export Sales Report'),
        ];
    }

    protected function exportReport()
    {
        $from = Session::get('supervisor_date_from', now()->startOfDay()->toDateTimeString());
        $to = Session::get('supervisor_date_to', now()->endOfDay()->toDateTimeString());

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
