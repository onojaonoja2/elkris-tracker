<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class LeadStatsWidget extends StatsOverviewWidget
{
    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    protected function getStats(): array
    {
        $leadId = auth()->id();

        $reps = User::where('lead_id', $leadId)->where('role', 'rep')->get();
        $repIds = $reps->pluck('id');

        $portfolioCustomers = Customer::whereHas('leads', fn ($q) => $q->where('users.id', $leadId));
        $totalPortfolio = $portfolioCustomers->count();
        $convertedPortfolio = Customer::whereHas('leads', fn ($q) => $q->where('users.id', $leadId))->whereHas('orders')->count();
        $conversionRate = $totalPortfolio > 0 ? round(($convertedPortfolio / $totalPortfolio) * 100, 1) : 0;

        $customersCount = Customer::whereIn('rep_id', $repIds)
            ->where('rep_acceptance_status', 'accepted')
            ->count();

        $pendingAssignments = Customer::where('lead_id', $leadId)
            ->where('rep_acceptance_status', 'pending')
            ->count();

        $submissionsWaiting = Customer::whereNotNull('agent_id')
            ->whereNull('rep_id')
            ->count();

        return [
            Stat::make('Team Reps', $reps->count())
                ->description('Active representatives')
                ->icon('heroicon-o-users')
                ->color('info'),
            Stat::make('Portfolio', $totalPortfolio)
                ->description($convertedPortfolio.' converted ('.$conversionRate.'%)')
                ->icon('heroicon-o-user-group')
                ->color('success'),
            Stat::make('Pending Assignments', $pendingAssignments)
                ->description('Awaiting rep acceptance')
                ->icon('heroicon-o-clock')
                ->color('warning'),
            Stat::make('Submissions Waiting', $submissionsWaiting)
                ->description('Ready for assignment')
                ->icon('heroicon-o-inbox-stack')
                ->color('primary'),
        ];
    }

    public static function canView(): bool
    {
        return auth()->user()->role === 'lead';
    }
}
