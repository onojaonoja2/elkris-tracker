<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class LeadStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $leadId = auth()->id();

        // Get all reps under this lead
        $reps = User::where('lead_id', $leadId)->where('role', 'rep')->get();
        $repIds = $reps->pluck('id');

        // Count customers assigned to those reps
        $customersCount = Customer::whereIn('rep_id', $repIds)
            ->where('rep_acceptance_status', 'accepted')
            ->count();

        // Count pending assignments
        $pendingAssignments = Customer::where('lead_id', $leadId)
            ->where('rep_acceptance_status', 'pending')
            ->count();

        // Count field agent submissions waiting for assignment
        $submissionsWaiting = Customer::whereNotNull('agent_id')
            ->whereNull('rep_id')
            ->whereNull('lead_id')
            ->count();

        return [
            Stat::make('Team Reps', $reps->count())
                ->description('Active representatives')
                ->icon('heroicon-o-users')
                ->color('info'),
            Stat::make('Active Customers', $customersCount)
                ->description('Under team portfolio')
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
