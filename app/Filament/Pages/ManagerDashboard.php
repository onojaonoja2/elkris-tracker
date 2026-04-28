<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ManagerConversionWidget;
use App\Filament\Widgets\ManagerPortfolioPerAgentWidget;
use App\Filament\Widgets\ManagerStatsWidget;
use App\Filament\Widgets\OrdersPerCityChart;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\Session;

class ManagerDashboard extends BaseDashboard
{
    protected static string $routePath = '/manager-dashboard';

    protected static ?string $slug = 'manager-dashboard';

    protected static ?int $navigationSort = -1;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->role === 'manager' || auth()->user()->role === 'admin';
    }

    public static function canViewNavigation(): bool
    {
        return auth()->user()->role === 'manager' || auth()->user()->role === 'admin';
    }

    public function mount()
    {
        if (! auth()->check() || ! in_array(auth()->user()->role, ['manager', 'admin'])) {
            return redirect()->to(Dashboard::getUrl([], isAbsolute: false, panel: 'admin'));
        }
    }

    public function getHeaderWidgets(): array
    {
        return [
            ManagerStatsWidget::class,
        ];
    }

    public function getWidgets(): array
    {
        return [
            ManagerPortfolioPerAgentWidget::class,
            ManagerConversionWidget::class,
            OrdersPerCityChart::class,
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
                }),
        ];
    }
}
