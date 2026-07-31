<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Support\DashboardDateScope;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class OrderStatsWidget extends BaseWidget
{
    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return auth()->user()->hasAnyRole([
            'rep',
            'lead',
            'sales',
            'community_sales_representative',
            'open_market',
            'retail_market',
            'supervisor',
            'accountant',
            'general_accountant',
            'manager',
            'general_manager',
            'admin',
        ]);
    }

    protected function getStats(): array
    {
        $user = auth()->user();
        $role = $user->getPrimaryRole();

        if (in_array($role, ['rep', 'lead', 'sales', 'community_sales_representative', 'open_market', 'retail_market'], true)) {
            return $this->agentStats($user->id);
        }

        if ($role === 'supervisor') {
            return $this->supervisorStats();
        }

        return $this->managementStats();
    }

    /**
     * @return array<int, Stat>
     */
    private function agentStats(int $userId): array
    {
        [$from, $to] = DashboardDateScope::fromSession();

        $baseQuery = Order::where('user_id', $userId)
            ->where('is_migrated_order', false)
            ->whereBetween('created_at', [$from, $to]);

        $total = (clone $baseQuery)->sum('total_price');
        $pending = (clone $baseQuery)->where('status', OrderStatus::Pending)->sum('total_price');
        $delivered = (clone $baseQuery)->where('status', OrderStatus::Delivered)->sum('total_price');

        return [
            Stat::make('My Total Orders', '₦'.number_format($total))
                ->description('All-time order value')
                ->icon('heroicon-o-shopping-cart')
                ->color('info')
                ->extraAttributes(['class' => 'cursor-pointer', 'wire:click' => "\$dispatch('open-order-breakdown', { category: 'my' })"]),
            Stat::make('My Pending Orders', '₦'.number_format($pending))
                ->description('Awaiting delivery')
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->extraAttributes(['class' => 'cursor-pointer', 'wire:click' => "\$dispatch('open-order-breakdown', { category: 'pending' })"]),
            Stat::make('My Delivered Orders', '₦'.number_format($delivered))
                ->description('Completed orders')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->extraAttributes(['class' => 'cursor-pointer', 'wire:click' => "\$dispatch('open-order-breakdown', { category: 'delivered' })"]),
        ];
    }

    /**
     * @return array<int, Stat>
     */
    private function supervisorStats(): array
    {
        [$from, $to] = DashboardDateScope::fromSession();

        $csrIds = User::where('role', 'community_sales_representative')->active()->pluck('id');
        $baseQuery = Order::whereIn('user_id', $csrIds)
            ->where('is_migrated_order', false)
            ->whereBetween('created_at', [$from, $to]);

        $total = (clone $baseQuery)->sum('total_price');
        $pending = (clone $baseQuery)->where('status', OrderStatus::Pending)->sum('total_price');
        $delivered = (clone $baseQuery)->where('status', OrderStatus::Delivered)->sum('total_price');

        return [
            Stat::make('CSR Orders Total', '₦'.number_format($total))
                ->description('All CSR orders')
                ->icon('heroicon-o-shopping-cart')
                ->color('info')
                ->extraAttributes(['class' => 'cursor-pointer', 'wire:click' => "\$dispatch('open-order-breakdown', { category: 'csr' })"]),
            Stat::make('CSR Pending Orders', '₦'.number_format($pending))
                ->description('Awaiting delivery')
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->extraAttributes(['class' => 'cursor-pointer', 'wire:click' => "\$dispatch('open-order-breakdown', { category: 'pending' })"]),
            Stat::make('CSR Delivered Orders', '₦'.number_format($delivered))
                ->description('Completed orders')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->extraAttributes(['class' => 'cursor-pointer', 'wire:click' => "\$dispatch('open-order-breakdown', { category: 'delivered' })"]),
        ];
    }

    /**
     * @return array<int, Stat>
     */
    private function managementStats(): array
    {
        [$from, $to] = DashboardDateScope::fromSession();

        $baseQuery = Order::where('is_migrated_order', false)
            ->whereBetween('created_at', [$from, $to]);

        $total = (clone $baseQuery)->sum('total_price');
        $pending = (clone $baseQuery)->where('status', OrderStatus::Pending)->sum('total_price');
        $delivered = (clone $baseQuery)->where('status', OrderStatus::Delivered)->sum('total_price');

        $stats = [
            Stat::make('Total Orders Value', '₦'.number_format($total))
                ->description('Across all channels')
                ->icon('heroicon-o-shopping-cart')
                ->color('info')
                ->extraAttributes(['class' => 'cursor-pointer', 'wire:click' => "\$dispatch('open-order-breakdown', { category: 'total' })"]),
            Stat::make('Pending Orders Value', '₦'.number_format($pending))
                ->description('Awaiting delivery')
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->extraAttributes(['class' => 'cursor-pointer', 'wire:click' => "\$dispatch('open-order-breakdown', { category: 'pending' })"]),
            Stat::make('Delivered Orders Value', '₦'.number_format($delivered))
                ->description('Completed orders')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->extraAttributes(['class' => 'cursor-pointer', 'wire:click' => "\$dispatch('open-order-breakdown', { category: 'delivered' })"]),
        ];

        $categories = [
            ['label' => 'CSR Orders', 'role' => 'community_sales_representative', 'icon' => 'heroicon-o-users'],
            ['label' => 'Open Market Orders', 'role' => 'open_market', 'icon' => 'heroicon-o-shopping-bag'],
            ['label' => 'Retail Market Orders', 'role' => 'retail_market', 'icon' => 'heroicon-o-building-storefront'],
        ];

        foreach ($categories as $category) {
            $categoryTotal = (clone $baseQuery)
                ->whereHas('user', fn ($q) => $q->where('role', $category['role']))
                ->sum('total_price');

            $categoryPending = (clone $baseQuery)
                ->where('status', OrderStatus::Pending)
                ->whereHas('user', fn ($q) => $q->where('role', $category['role']))
                ->sum('total_price');

            $categoryDelivered = (clone $baseQuery)
                ->where('status', OrderStatus::Delivered)
                ->whereHas('user', fn ($q) => $q->where('role', $category['role']))
                ->sum('total_price');

            $stats[] = Stat::make($category['label'], '₦'.number_format($categoryTotal))
                ->description('Pending: ₦'.number_format($categoryPending).' | Delivered: ₦'.number_format($categoryDelivered))
                ->icon($category['icon'])
                ->color('primary')
                ->extraAttributes(['class' => 'cursor-pointer', 'wire:click' => "\$dispatch('open-order-breakdown', { category: '{$category['role']}' })"]);
        }

        return $stats;
    }
}
