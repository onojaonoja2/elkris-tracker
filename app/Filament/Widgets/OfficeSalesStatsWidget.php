<?php

namespace App\Filament\Widgets;

use App\Models\SalesRecord;
use App\Models\User;
use App\Support\DashboardDateScope;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class OfficeSalesStatsWidget extends StatsOverviewWidget
{
    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    protected function getStats(): array
    {
        [$from, $to] = DashboardDateScope::fromSession();

        $query = SalesRecord::query()->where('agent_type', 'sales');

        if (auth()->user()->hasRole('sales')) {
            $query->where('agent_id', auth()->id());
        } else {
            $agentIds = User::where('role', 'sales')->active()->pluck('id');
            $query->whereIn('agent_id', $agentIds);
        }

        $range = (clone $query)->whereBetween('created_at', [$from, $to]);

        $total = (float) $range->sum('total_value');
        $approved = (clone $range)->where('status', 'approved')->count();
        $pending = (clone $range)->where('status', 'pending')->count();

        return [
            Stat::make('Office Sales', '₦'.number_format($total, 2))
                ->description("Approved: {$approved} | Pending approval: {$pending}")
                ->icon('heroicon-o-building-office')
                ->color('success')
                ->extraAttributes(['class' => 'cursor-pointer', 'wire:click' => "\$dispatch('open-office-sales-breakdown')"]),
        ];
    }

    public static function canView(): bool
    {
        return auth()->user()->hasAnyRole([
            'sales',
            'manager',
            'admin',
            'general_manager',
            'accountant',
            'general_accountant',
        ]);
    }
}
