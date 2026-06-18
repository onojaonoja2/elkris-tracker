<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Users\UserResource;
use App\Models\AgentStock;
use App\Models\SalesRecord;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Session;

class SupervisorStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        $from = Session::get('supervisor_date_from', now()->startOfDay()->toDateTimeString());
        $to = Session::get('supervisor_date_to', now()->endOfDay()->toDateTimeString());

        $csrIds = User::where('role', 'community_sales_representative')->pluck('id');

        $csrCount = $csrIds->count();

        $salesQuery = SalesRecord::whereIn('agent_id', $csrIds)
            ->whereBetween('created_at', [$from, $to]);

        $salesCount = (clone $salesQuery)->count();
        $salesRevenue = (clone $salesQuery)->sum('total_value');
        $pendingCount = SalesRecord::whereIn('agent_id', $csrIds)
            ->where('status', 'receipt_uploaded')
            ->count();
        $stockUnits = AgentStock::whereIn('user_id', $csrIds)->sum('quantity');

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

            Stat::make('Pending', number_format($pendingCount))
                ->description('Awaiting approval')
                ->icon('heroicon-o-clock')
                ->color('warning'),

            Stat::make('Stock Units', number_format($stockUnits))
                ->description('Total CSR stock on hand')
                ->icon('heroicon-o-cube')
                ->color('gray'),
        ];
    }
}
