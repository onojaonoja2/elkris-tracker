<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasDashboardBreakdownModals;
use App\Filament\Pages\Concerns\HasDashboardDateFilter;
use App\Filament\Widgets\CsrOverviewWidget;
use App\Filament\Widgets\LeadAgentSubmissionsWidget;
use App\Filament\Widgets\LeadAssignedCsrOrdersWidget;
use App\Filament\Widgets\LeadCsrAssignmentWidget;
use App\Filament\Widgets\LeadCsrSubmissionsWidget;
use App\Filament\Widgets\LeadOrderAssignmentWidget;
use App\Filament\Widgets\LeadOrdersStatsWidget;
use App\Filament\Widgets\LeadOrdersWidget;
use App\Filament\Widgets\LeadPendingAssignmentsWidget;
use App\Filament\Widgets\LeadPersonalPortfolioWidget;
use App\Filament\Widgets\LeadPortfolioWidget;
use App\Filament\Widgets\LeadRejectedCustomersWidget;
use App\Filament\Widgets\LeadStatsWidget;
use App\Filament\Widgets\OrderStatsWidget;
use App\Filament\Widgets\UpcomingFollowUps;
use App\Models\Customer;
use App\Support\DashboardDateScope;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeadDashboard extends BaseDashboard
{
    use HasDashboardBreakdownModals;
    use HasDashboardDateFilter;

    protected static string $routePath = '/lead-dashboard';

    protected static ?string $slug = 'lead-dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = -1;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('lead');
    }

    public static function canViewNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('lead');
    }

    public static function getNavigationLabel(): string
    {
        return 'Dashboard';
    }

    public function mount()
    {
        if (! auth()->check() || ! auth()->user()->hasRole('lead')) {
            return redirect()->to(Dashboard::getUrl([], isAbsolute: false, panel: 'admin'));
        }
    }

    #[On('open-team-sales-breakdown')]
    public function openTeamSalesBreakdown(): void
    {
        $this->mountAction('teamSalesBreakdown');
    }

    protected function getTeamSalesBreakdownAction(): Action
    {
        return Action::make('teamSalesBreakdown')
            ->label('Team Sales Breakdown')
            ->icon('heroicon-o-currency-dollar')
            ->modalHeading('Team Sales Breakdown')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalContent(function (): View {
                return view('filament.team-sales-breakdown-modal');
            })
            ->visible(fn (): bool => auth()->user()->hasRole('lead'));
    }

    public function getHeaderWidgets(): array
    {
        return [
            LeadStatsWidget::class,
            LeadOrdersStatsWidget::class,
            OrderStatsWidget::class,
        ];
    }

    public function getWidgets(): array
    {
        return [
            LeadOrderAssignmentWidget::class,
            LeadAssignedCsrOrdersWidget::class,
            LeadCsrAssignmentWidget::class,
            LeadAgentSubmissionsWidget::class,
            LeadCsrSubmissionsWidget::class,
            LeadPendingAssignmentsWidget::class,
            LeadRejectedCustomersWidget::class,
            LeadPortfolioWidget::class,
            LeadPersonalPortfolioWidget::class,
            LeadOrdersWidget::class,
            CsrOverviewWidget::class,
            UpcomingFollowUps::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getDateFilterAction(),
            $this->getClearDateFilterAction(),
            $this->getOrderBreakdownAction(),
            $this->getTeamSalesBreakdownAction(),
            Action::make('exportPersonalPortfolio')
                ->label('Export')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->action(fn () => $this->exportPersonalPortfolio()),
        ];
    }

    protected function exportPersonalPortfolio(): StreamedResponse
    {
        $leadId = auth()->id();
        [$from, $to] = DashboardDateScope::fromSession();

        $customers = Customer::whereHas('leads', fn ($q) => $q->where('users.id', $leadId))
            ->whereDoesntHave('reps')
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at', 'desc')
            ->get();

        $filename = 'lead_personal_portfolio_'.now()->format('Y_m_d_H_i_s').'.csv';

        return response()->streamDownload(function () use ($customers) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, ['Customer Name', 'Phone', 'Address', 'City', 'State', 'Date Added']);

            foreach ($customers as $customer) {
                fputcsv($handle, [
                    $customer->customer_name,
                    $customer->phone_number,
                    $customer->address,
                    $customer->city,
                    $customer->state,
                    $customer->created_at->format('d/m/Y'),
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
