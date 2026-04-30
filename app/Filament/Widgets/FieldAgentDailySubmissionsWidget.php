<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class FieldAgentDailySubmissionsWidget extends BaseWidget
{
    public static function canView(): bool
    {
        return auth()->user() && auth()->user()->role === 'field_agent';
    }

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    protected function getStats(): array
    {
        $count = Customer::where('agent_id', auth()->id())
            ->whereDate('created_at', today())
            ->count();

        return [
            Stat::make('Customers Submitted Today', $count)
                ->icon('heroicon-o-users')
                ->color('success'),
        ];
    }
}
