<?php

namespace App\Filament\Resources\TrialOrders\Widgets;

use App\Models\Customer;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StockMetricsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $user = auth()->user();

        // 1. Raw Mathematical Liability
        $outstandingLiability = (float) $user->stock_balance;

        // 2. Unverified drops sitting in the Customers table awaiting Supervisor verification
        $pendingConfirmations = (float) Customer::where('agent_id', $user->id)
            ->where('is_payment_verified', false)
            ->where('trial_order_purchase', 'yes')
            ->sum('total_price');

        // 3. Drops successfully cleared by a supervisor
        $clearedPayments = (float) Customer::where('agent_id', $user->id)
            ->where('is_payment_verified', true)
            ->where('trial_order_purchase', 'yes')
            ->sum('total_price');

        return [
            Stat::make('Pending Confirmations (Yet to Confirm)', '₦'.number_format($pendingConfirmations, 2))
                ->description('Active drops awaiting supervisor validation')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('Outstanding Liability', '₦'.number_format($outstandingLiability, 2))
                ->description('Total financial value owed to Elkris foods')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('danger'),

            Stat::make('Cleared Payments (Lifetime)', '₦'.number_format($clearedPayments, 2))
                ->description('Values completely recovered & successfully verified')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
        ];
    }
}
