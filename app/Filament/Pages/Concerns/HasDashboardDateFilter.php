<?php

namespace App\Filament\Pages\Concerns;

use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Session;

trait HasDashboardDateFilter
{
    public function getDateFilterAction(): Action
    {
        return Action::make('filterDates')
            ->label('Filter')
            ->icon('heroicon-o-funnel')
            ->button()
            ->form([
                DatePicker::make('date_from')
                    ->label('From')
                    ->default(fn () => Session::get('dashboard_date_from', now()->startOfDay()))
                    ->required(),
                DatePicker::make('date_to')
                    ->label('To')
                    ->default(fn () => Session::get('dashboard_date_to', now()->endOfDay()))
                    ->required(),
            ])
            ->action(function (array $data) {
                Session::put('dashboard_date_from', $data['date_from']);
                Session::put('dashboard_date_to', $data['date_to']);
                $this->dispatch('refresh-dashboard');
                Notification::make()->title('Date filter applied')->success()->send();
            })
            ->modalHeading('Filter by Date Range');
    }

    public function getClearDateFilterAction(): Action
    {
        return Action::make('clearFilter')
            ->label('Today')
            ->icon('heroicon-o-x-mark')
            ->color('gray')
            ->action(function () {
                Session::put('dashboard_date_from', now()->startOfDay()->toDateTimeString());
                Session::put('dashboard_date_to', now()->endOfDay()->toDateTimeString());
                $this->dispatch('refresh-dashboard');
            });
    }
}
