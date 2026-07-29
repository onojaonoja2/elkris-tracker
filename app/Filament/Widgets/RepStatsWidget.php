<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\PortfolioResource;
use App\Models\Customer;
use App\Models\Order;
use App\Models\SalesRecord;
use App\Models\User;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class RepStatsWidget extends StatsOverviewWidget
{
    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    protected function getStats(): array
    {
        $repId = auth()->id();

        $pendingCount = Customer::where('rep_id', $repId)
            ->where('rep_acceptance_status', 'pending')
            ->count();

        $portfolioCount = Customer::where('rep_id', $repId)
            ->where('rep_acceptance_status', 'accepted')
            ->count();

        $convertedCount = Customer::where('rep_id', $repId)
            ->where('rep_acceptance_status', 'accepted')
            ->whereHas('orders', fn ($q) => $q->where('is_migrated_order', false))
            ->count();

        $conversionRate = $portfolioCount > 0 ? round(($convertedCount / $portfolioCount) * 100, 1) : 0;

        $ordersTodayQuery = Order::where('user_id', $repId)
            ->whereDate('created_at', Carbon::today())
            ->where('is_migrated_order', false);

        $ordersToday = $ordersTodayQuery->count();
        $ordersTodayValue = number_format($ordersTodayQuery->sum('total_price'), 2);

        $attachedCsrIds = User::where('portfolio_agent_id', $repId)
            ->where('role', 'community_sales_representative')
            ->pluck('id');

        $teamSalesValue = SalesRecord::whereIn('agent_id', $attachedCsrIds)
            ->where('status', 'approved')
            ->sum('total_value');

        $orderValueAccrued = Order::where('user_id', $repId)
            ->where('is_migrated_order', false)
            ->sum('total_price');

        if ($attachedCsrIds->isNotEmpty()) {
            $stats[] = Stat::make('Team Sales Value', '₦'.number_format($teamSalesValue, 2))
                ->description('From '.$attachedCsrIds->count().' attached CSR(s)')
                ->icon('heroicon-o-currency-dollar')
                ->color('success');
        }

        return [
            Stat::make('Pending Assignments', $pendingCount)
                ->description('Awaiting your acceptance')
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->url(PortfolioResource::getUrl('index')),
            Stat::make('Total Portfolio', $portfolioCount)
                ->description('Customers in portfolio')
                ->icon('heroicon-o-users')
                ->color('info')
                ->url(PortfolioResource::getUrl('index')),
            Stat::make('Converted', $convertedCount)
                ->description($conversionRate.'% conversion rate')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->url(PortfolioResource::getUrl('index')),
            Stat::make('Orders Today', $ordersToday)
                ->description('₦'.$ordersTodayValue.' total value')
                ->icon('heroicon-o-shopping-cart')
                ->color('primary')
                ->url(OrderResource::getUrl('index')),
            Stat::make('Order Value Accrued', '₦'.number_format($orderValueAccrued, 2))
                ->description('Lifetime from your orders')
                ->icon('heroicon-o-banknotes')
                ->color('info'),
        ];
    }

    public static function canView(): bool
    {
        return auth()->user()->hasRole('rep');
    }
}
