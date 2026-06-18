<?php

namespace App\Filament\Widgets;

use App\Models\AgentStock;
use App\Models\Customer;
use App\Models\SalesRecord;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CsrStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        $user = auth()->user();
        $stockQuantity = AgentStock::where('user_id', $user->id)->sum('quantity');
        $totalCustomers = Customer::where('agent_id', $user->id)->count();
        $totalSalesRecords = SalesRecord::where('agent_id', $user->id)->count();
        $pairedAgent = $user->portfolioAgent?->name ?? 'Not paired';

        $creditPending = SalesRecord::where('agent_id', $user->id)
            ->where('is_credit', true)
            ->where('status', 'approved')
            ->where('credit_status', 'pending_payment')
            ->sum('total_value');

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
                ->description('Total sales records')
                ->color('warning')
                ->descriptionIcon('heroicon-o-document-text'),
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
