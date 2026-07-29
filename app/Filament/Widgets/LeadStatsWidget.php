<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Customer;
use App\Models\Order;
use App\Models\SalesRecord;
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
        $convertedPortfolio = Customer::whereHas('leads', fn ($q) => $q->where('users.id', $leadId))->whereHas('orders', fn ($q) => $q->where('is_migrated_order', false))->count();
        $conversionRate = $totalPortfolio > 0 ? round(($convertedPortfolio / $totalPortfolio) * 100, 1) : 0;

        $customersCount = Customer::whereIn('rep_id', $repIds)
            ->where('rep_acceptance_status', 'accepted')
            ->count();

        $pendingAssignments = Customer::where('lead_id', $leadId)
            ->where('rep_acceptance_status', 'pending')
            ->count();

        $submissionsWaiting = Customer::whereNotNull('agent_id')
            ->whereNull('rep_id')
            ->whereNull('lead_id')
            ->count();

        $csrIds = User::whereIn('portfolio_agent_id', $repIds)
            ->where('role', 'community_sales_representative')
            ->pluck('id');

        $teamSalesValue = SalesRecord::whereIn('agent_id', $csrIds)
            ->where('status', 'approved')
            ->sum('total_value');

        $orderValueAccrued = Order::whereIn('user_id', $repIds)
            ->where('is_migrated_order', false)
            ->sum('total_price');

        return [
            Stat::make('Team Reps', $reps->count())
                ->description('Active representatives')
                ->icon('heroicon-o-users')
                ->color('info')
                ->url(UserResource::getUrl('index')),
            Stat::make('Portfolio', $totalPortfolio)
                ->description($convertedPortfolio.' converted ('.$conversionRate.'%)')
                ->icon('heroicon-o-user-group')
                ->color('success')
                ->url(CustomerResource::getUrl('index')),
            Stat::make('Pending Assignments', $pendingAssignments)
                ->description('Awaiting rep acceptance')
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->url(CustomerResource::getUrl('index')),
            Stat::make('Submissions Waiting', $submissionsWaiting)
                ->description('Ready for assignment')
                ->icon('heroicon-o-inbox-stack')
                ->color('primary')
                ->url(CustomerResource::getUrl('index')),
            Stat::make('Team Sales Value', '₦'.number_format($teamSalesValue, 2))
                ->description('From CSRs under your team')
                ->icon('heroicon-o-currency-dollar')
                ->color('success'),
            Stat::make('Order Value Accrued', '₦'.number_format($orderValueAccrued, 2))
                ->description('Lifetime from team orders')
                ->icon('heroicon-o-banknotes')
                ->color('info'),
        ];
    }

    public static function canView(): bool
    {
        return auth()->user()->hasRole('lead');
    }
}
