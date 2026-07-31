<?php

namespace App\Filament\Pages;

use App\Enums\OrderStatus;
use App\Filament\Pages\Concerns\HasDashboardBreakdownModals;
use App\Filament\Pages\Concerns\HasDashboardDateFilter;
use App\Filament\Widgets\CsrOverviewWidget;
use App\Filament\Widgets\OrderStatsWidget;
use App\Filament\Widgets\RepAssignedCsrOrdersWidget;
use App\Filament\Widgets\RepOrderAssignmentWidget;
use App\Filament\Widgets\RepPendingAssignmentsWidget;
use App\Filament\Widgets\RepPortfolioWidget;
use App\Filament\Widgets\RepStatsWidget;
use App\Filament\Widgets\UpcomingFollowUps;
use App\Models\Order;
use App\Support\DashboardDateScope;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RepDashboard extends BaseDashboard
{
    use HasDashboardBreakdownModals;
    use HasDashboardDateFilter;

    protected static string $routePath = '/rep-dashboard';

    protected static ?string $slug = 'rep-dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = -1;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('rep');
    }

    public static function canViewNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('rep');
    }

    public static function getNavigationLabel(): string
    {
        return 'Dashboard';
    }

    public function mount()
    {
        if (! auth()->check() || ! auth()->user()->hasRole('rep')) {
            return redirect()->to(Dashboard::getUrl([], isAbsolute: false, panel: 'admin'));
        }
    }

    public function getHeaderWidgets(): array
    {
        return [
            RepStatsWidget::class,
            OrderStatsWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getDateFilterAction(),
            $this->getClearDateFilterAction(),
            $this->getOrderBreakdownAction(),
            Action::make('exportReport')
                ->label('Export')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(fn () => $this->exportReport()),
        ];
    }

    protected function exportReport(): StreamedResponse
    {
        $repId = auth()->id();
        [$from, $to] = DashboardDateScope::fromSession();

        $records = Order::where('user_id', $repId)
            ->where('is_migrated_order', false)
            ->whereNotNull('assigned_to')
            ->where('status', '!=', OrderStatus::Delivered)
            ->whereBetween('created_at', [$from, $to])
            ->with(['customer', 'assignedTo', 'products'])
            ->orderBy('created_at', 'desc')
            ->get();

        $filename = 'rep_assigned_orders_report_'.now()->format('Y_m_d_H_i_s').'.csv';

        return response()->streamDownload(function () use ($records) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, [
                'Date',
                'Order #',
                'Customer',
                'CSR Name',
                'CSR Phone',
                'CSR Email',
                'Items',
                'Value (₦)',
                'Status',
            ]);

            foreach ($records as $record) {
                $items = $record->products
                    ->map(fn ($product) => "{$product->quantity}x {$product->product_name} ({$product->grammage}g)")
                    ->implode('; ');

                fputcsv($handle, [
                    $record->created_at->format('d/m/Y H:i'),
                    '#'.$record->id,
                    $record->customer?->customer_name ?? '-',
                    $record->assignedTo?->name ?? 'N/A',
                    $record->assignedTo?->phone ?? '-',
                    $record->assignedTo?->email ?? '-',
                    $items,
                    number_format($record->total_price, 2),
                    $record->status->value,
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function getWidgets(): array
    {
        return [
            RepOrderAssignmentWidget::class,
            RepAssignedCsrOrdersWidget::class,
            RepPendingAssignmentsWidget::class,
            RepPortfolioWidget::class,
            CsrOverviewWidget::class,
            UpcomingFollowUps::class,
        ];
    }
}
