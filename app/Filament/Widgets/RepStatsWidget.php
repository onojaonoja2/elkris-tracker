<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\PortfolioResource;
use App\Models\Customer;
use App\Models\Order;
use App\Models\SalesRecord;
use App\Models\User;
use App\Support\DashboardDateScope;
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
        [$from, $to] = DashboardDateScope::fromSession();

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

        $ordersQuery = Order::where('user_id', $repId)
            ->where('is_migrated_order', false)
            ->whereBetween('created_at', [$from, $to]);

        $ordersToday = $ordersQuery->count();
        $ordersTodayValue = number_format($ordersQuery->sum('total_price'), 2);

        $attachedCsrIds = User::where('portfolio_agent_id', $repId)
            ->where('role', 'community_sales_representative')
            ->pluck('id');

        $teamSalesValue = SalesRecord::whereIn('agent_id', $attachedCsrIds)
            ->where('status', 'approved')
            ->whereBetween('created_at', [$from, $to])
            ->sum('total_value');

        $orderValueAccrued = Order::where('user_id', $repId)
            ->where('is_migrated_order', false)
            ->whereBetween('created_at', [$from, $to])
            ->sum('total_price');

        $stats = [];

        if ($attachedCsrIds->isNotEmpty()) {
            $stats[] = Stat::make('Team Sales Value', '₦'.number_format($teamSalesValue, 2))
                ->description('From '.$attachedCsrIds->count().' attached CSR(s)')
                ->icon('heroicon-o-currency-dollar')
                ->color('success');
        }

        $stats[] = Stat::make('Pending Assignments', $pendingCount)
            ->description('Awaiting your acceptance')
            ->icon('heroicon-o-clock')
            ->color('warning')
            ->url(PortfolioResource::getUrl('index'));

        $stats[] = Stat::make('Total Portfolio', $portfolioCount)
            ->description('Customers in portfolio')
            ->icon('heroicon-o-users')
            ->color('info')
            ->url(PortfolioResource::getUrl('index'));

        $stats[] = Stat::make('Converted', $convertedCount)
            ->description($conversionRate.'% conversion rate')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->url(PortfolioResource::getUrl('index'));

        $stats[] = Stat::make('Orders', $ordersToday)
            ->description('₦'.$ordersTodayValue.' value in selected range')
            ->icon('heroicon-o-shopping-cart')
            ->color('primary')
            ->url(OrderResource::getUrl('index'));

        $stats[] = Stat::make('Order Value Accrued', '₦'.number_format($orderValueAccrued, 2))
            ->description('Order value in selected range')
            ->icon('heroicon-o-banknotes')
            ->color('info');

        return $stats;
    }

    public static function canView(): bool
    {
        return auth()->user()->hasRole('rep');
    }
}
