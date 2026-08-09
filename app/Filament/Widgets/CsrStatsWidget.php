<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\AgentStock;
use App\Models\Customer;
use App\Models\Order;
use App\Models\SalesRecord;
use App\Support\DashboardDateScope;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class CsrStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    protected function getStats(): array
    {
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        [$from, $to] = DashboardDateScope::fromSession();

        $stockQuantity = AgentStock::where('user_id', $user->id)->sum('quantity');
        $totalCustomers = Customer::where('agent_id', $user->id)->count();
        $totalSalesRecords = SalesRecord::where('agent_id', $user->id)
            ->whereBetween('created_at', [$from, $to])
            ->count();
        $pairedAgent = $user->portfolioAgent?->name ?? 'Not paired';

        $creditPending = SalesRecord::outstanding()
            ->where('agent_id', $user->id)
            ->whereBetween('created_at', [$from, $to])
            ->sum('total_value');

        $ordersQuery = Order::where('assigned_to', $user->id)
            ->where('is_migrated_order', false)
            ->whereBetween('created_at', [$from, $to]);

        $orderCount = (clone $ordersQuery)->count();
        $orderValue = (clone $ordersQuery)->sum('total_price');
        $completedOrders = (clone $ordersQuery)->where('status', OrderStatus::Delivered)->count();

        $firstTimeOrderCount = Order::where('assigned_to', $user->id)
            ->where('is_migrated_order', false)
            ->whereBetween('created_at', [$from, $to])
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('orders as earlier')
                    ->whereColumn('earlier.customer_id', 'orders.customer_id')
                    ->where('earlier.is_migrated_order', false)
                    ->whereColumn('earlier.id', '<', 'orders.id');
            })
            ->count();

        return [
            Stat::make('Stock Quantity', number_format($stockQuantity))
                ->description('Current stock on hand')
                ->color('success')
                ->descriptionIcon('heroicon-o-cube'),
            Stat::make('Total Customers', number_format($totalCustomers))
                ->description('Customers added by you')
                ->color('info')
                ->descriptionIcon('heroicon-o-users'),
            Stat::make('Sales Records', number_format($totalSalesRecords))
                ->description('Sales records in selected range')
                ->color('warning')
                ->descriptionIcon('heroicon-o-document-text'),
            Stat::make('Orders', number_format($orderCount))
                ->description('₦'.number_format($orderValue, 2).' value in selected range')
                ->color('primary')
                ->descriptionIcon('heroicon-o-shopping-cart'),
            Stat::make('Completed Orders', number_format($completedOrders))
                ->description('Delivered orders in selected range')
                ->color('success')
                ->descriptionIcon('heroicon-o-check-circle'),
            Stat::make('First-Time Orders', number_format($firstTimeOrderCount))
                ->description('Orders from new customers')
                ->color('info')
                ->descriptionIcon('heroicon-o-star'),
            Stat::make('Credit Pending', '₦'.number_format($creditPending))
                ->description('Outstanding credit collections')
                ->color('danger')
                ->descriptionIcon('heroicon-o-clock'),
            Stat::make('Portfolio Agent', $pairedAgent)
                ->description('Your paired Elkris Portfolio Agent')
                ->color('primary')
                ->descriptionIcon('heroicon-o-briefcase'),
        ];
    }
}
