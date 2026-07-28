<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\User;
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
        $repIds = User::where('lead_id', $leadId)->where('role', 'rep')->pluck('id')->toArray();
        $allUserIds = array_merge([$leadId], $repIds);

        $leadTotal = Order::where('user_id', $leadId)->where('status', 'delivered')->where('is_migrated_order', false)->sum('total_price');
        $repTotal = Order::whereIn('user_id', $repIds)->where('status', 'delivered')->where('is_migrated_order', false)->sum('total_price');
        $teamTotal = $leadTotal + $repTotal;

        $leadOrders = Order::where('user_id', $leadId)->where('is_migrated_order', false)->count();
        $repOrders = Order::whereIn('user_id', $repIds)->where('is_migrated_order', false)->count();
        $teamOrders = $leadOrders + $repOrders;

        return [
            Stat::make('My Orders', $leadOrders)
                ->description('₦'.number_format($leadTotal, 2))
                ->icon('heroicon-o-shopping-bag')
                ->color('info')
                ->url(OrderResource::getUrl('index')),
            Stat::make('Rep Orders', $repOrders)
                ->description('₦'.number_format($repTotal, 2))
                ->icon('heroicon-o-user-group')
                ->color('primary')
                ->url(OrderResource::getUrl('index')),
            Stat::make('Team Orders', $teamOrders)
                ->description('₦'.number_format($teamTotal, 2))
                ->icon('heroicon-o-building-office')
                ->color('success')
                ->url(OrderResource::getUrl('index')),
        ];
    }

    public static function canView(): bool
    {
        return auth()->user()->hasRole('lead');
    }
}
