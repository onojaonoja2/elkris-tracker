<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Users\UserResource;
use App\Models\AgentStock;
use App\Models\Order;
use App\Models\SalesRecord;
use App\Models\User;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\On;

class SupervisorStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    protected function getStats(): array
    {
        $from = Session::get('supervisor_date_from', now()->startOfDay()->toDateTimeString());
        $to = Session::get('supervisor_date_to', now()->endOfDay()->toDateTimeString());

        $csrIds = User::where('role', 'community_sales_representative')->active()->pluck('id');

        $csrCount = $csrIds->count();

        $salesQuery = SalesRecord::whereIn('agent_id', $csrIds)
            ->whereBetween('created_at', [$from, $to]);

        $salesCount = (clone $salesQuery)->count();
        $salesRevenue = SalesRecord::revenue($csrIds->all(), Carbon::parse($from), Carbon::parse($to));
        $pendingCount = SalesRecord::whereIn('agent_id', $csrIds)
            ->whereIn('status', ['pending', 'receipt_uploaded'])
            ->whereBetween('created_at', [$from, $to])
            ->count();
        $stockUnits = AgentStock::whereIn('user_id', $csrIds)->sum('quantity');

        $creditOutstanding = SalesRecord::outstanding()
            ->whereIn('agent_id', $csrIds)
            ->whereBetween('created_at', [$from, $to])
            ->sum('total_value');

        $pendingOrders = Order::whereIn('user_id', $csrIds)
            ->where('status', 'pending')
            ->where('is_migrated_order', false)
            ->whereBetween('created_at', [$from, $to])
            ->count();
        $ordersValue = Order::whereIn('user_id', $csrIds)
            ->where('status', 'pending')
            ->where('is_migrated_order', false)
            ->whereBetween('created_at', [$from, $to])
            ->sum('total_price');

        return [
            Stat::make('CSRs', number_format($csrCount))
                ->description('Active CSRs')
                ->icon('heroicon-o-users')
                ->color('primary')
                ->url(UserResource::getUrl('index')),

            Stat::make('Sales', number_format($salesCount))
                ->description('Sales records in period')
                ->icon('heroicon-o-document-text')
                ->color('info'),

            Stat::make('Revenue', '₦'.number_format($salesRevenue, 2))
                ->description('Total sales value in period')
                ->icon('heroicon-o-banknotes')
                ->color('success'),

            Stat::make('Pending Orders', number_format($pendingOrders))
                ->description('₦'.number_format($ordersValue, 2).' total value')
                ->icon('heroicon-o-shopping-cart')
                ->color($pendingOrders > 0 ? 'warning' : 'gray')
                ->extraAttributes(['class' => 'cursor-pointer', 'wire:click' => "\$dispatch('open-order-breakdown', { category: 'pending' })"]),

            Stat::make('Pending Approvals', number_format($pendingCount))
                ->description('Receipts awaiting approval')
                ->icon('heroicon-o-clock')
                ->color('warning'),

            Stat::make('Credit Outstanding', '₦'.number_format($creditOutstanding))
                ->description('Pending credit collection')
                ->icon('heroicon-o-clock')
                ->color('danger')
                ->extraAttributes(['class' => 'cursor-pointer', 'wire:click' => "\$dispatch('open-credit-breakdown', { category: 'csr' })"]),

            Stat::make('Stock Units', number_format($stockUnits))
                ->description('Total CSR stock on hand')
                ->icon('heroicon-o-cube')
                ->color('gray'),
        ];
    }
}
