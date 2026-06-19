<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\DamagedReturnsBreakdownWidget;
use App\Filament\Widgets\GeneralManagerStatsWidget;
use App\Filament\Widgets\ManagerConversionWidget;
use App\Filament\Widgets\ManagerCreditSalesWidget;
use App\Filament\Widgets\ManagerCustomerSubmissionsWidget;
use App\Filament\Widgets\ManagerCustomersWidget;
use App\Filament\Widgets\ManagerPeopleByStateWidget;
use App\Filament\Widgets\ManagerPortfolioPerAgentWidget;
use App\Filament\Widgets\ManagerSalesRecordsByStateWidget;
use App\Filament\Widgets\ManagerStockLevelsOverviewWidget;
use App\Filament\Widgets\ManagerStockMovementsWidget;
use App\Filament\Widgets\ManagerTrialOrdersByStateWidget;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\Session;

class GeneralManagerDashboard extends BaseDashboard
{
    protected static string $routePath = '/general-manager-dashboard';

    protected static ?string $slug = 'general-manager-dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = -1;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->role === 'general_manager';
    }

    public static function canViewNavigation(): bool
    {
        return auth()->check() && auth()->user()->role === 'general_manager';
    }

    public function mount()
    {
        if (! auth()->check() || auth()->user()->role !== 'general_manager') {
            return redirect()->to(Dashboard::getUrl([], isAbsolute: false, panel: 'admin'));
        }
    }

    public function getHeaderWidgets(): array
    {
        return [
            GeneralManagerStatsWidget::class,
            ManagerCustomerSubmissionsWidget::class,
        ];
    }

    public function getWidgets(): array
    {
        return [
            ManagerPeopleByStateWidget::class,
            ManagerTrialOrdersByStateWidget::class,
            ManagerSalesRecordsByStateWidget::class,
            ManagerCreditSalesWidget::class,
            ManagerStockLevelsOverviewWidget::class,
            ManagerStockMovementsWidget::class,
            ManagerCustomersWidget::class,
            ManagerPortfolioPerAgentWidget::class,
            ManagerConversionWidget::class,
            DamagedReturnsBreakdownWidget::class,
        ];
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('filter_date')
                ->label('Filter by Date')
                ->icon('heroicon-o-calendar')
                ->color('secondary')
                ->form([
                    Select::make('preset')
                        ->options([
                            'today' => 'Today (8AM-5PM)',
                            'yesterday' => 'Yesterday',
                            'this_week' => 'This Week',
                            'this_month' => 'This Month',
                            'lifetime' => 'Lifetime',
                        ])
                        ->default('today')
                        ->required(),
                ])
                ->action(function (array $data) {
                    Session::put('manager_date_preset', $data['preset']);
                    $this->redirect($this->getUrl());
                })
                ->successNotificationTitle('Date filter applied'),
        ];
    }
}
