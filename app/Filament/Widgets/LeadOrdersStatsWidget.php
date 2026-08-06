<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\User;
use App\Support\DashboardDateScope;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class LeadOrdersStatsWidget extends StatsOverviewWidget
{
    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    protected function getStats(): array
    {
        $leadId = auth()->id();
        [$from, $to] = DashboardDateScope::fromSession();
        $repIds = User::where('lead_id', $leadId)->where('role', 'rep')->pluck('id')->toArray();

        $baseQuery = Order::where('is_migrated_order', false)
            ->whereBetween('created_at', [$from, $to]);

        $leadOrders = (clone $baseQuery)->where('user_id', $leadId)->count();
        $leadPending = (clone $baseQuery)->where('user_id', $leadId)->pendingDelivery()->sum('total_price');
        $leadDelivered = (clone $baseQuery)->where('user_id', $leadId)->where('status', OrderStatus::Delivered)->sum('total_price');

        $repOrders = (clone $baseQuery)->whereIn('user_id', $repIds)->count();
        $repPending = (clone $baseQuery)->whereIn('user_id', $repIds)->pendingDelivery()->sum('total_price');
        $repDelivered = (clone $baseQuery)->whereIn('user_id', $repIds)->where('status', OrderStatus::Delivered)->sum('total_price');

        $teamOrders = $leadOrders + $repOrders;
        $teamPending = $leadPending + $repPending;
        $teamDelivered = $leadDelivered + $repDelivered;

        return [
            Stat::make('My Orders', $leadOrders)
                ->description($this->valueBreakdown($leadPending, $leadDelivered))
                ->icon('heroicon-o-shopping-bag')
                ->color('info')
                ->url(OrderResource::getUrl('index')),
            Stat::make('Rep Orders', $repOrders)
                ->description($this->valueBreakdown($repPending, $repDelivered))
                ->icon('heroicon-o-user-group')
                ->color('primary')
                ->url(OrderResource::getUrl('index')),
            Stat::make('Team Orders', $teamOrders)
                ->description($this->valueBreakdown($teamPending, $teamDelivered))
                ->icon('heroicon-o-building-office')
                ->color('success')
                ->url(OrderResource::getUrl('index')),
        ];
    }

    private function valueBreakdown(float|int $pending, float|int $delivered): string
    {
        return 'Pending: ₦'.number_format($pending, 2).' | Delivered: ₦'.number_format($delivered, 2);
    }

    public static function canView(): bool
    {
        return auth()->user()->hasRole('lead');
    }
}
