<?php

namespace App\Filament\Widgets;

use App\Models\SalesRecord;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class AgentCreditSalesWidget extends BaseWidget
{
    protected static ?int $sort = 5;

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return in_array(auth()->user()->role, ['community_sales_representative', 'open_market', 'retail_market']);
    }

    protected function getStats(): array
    {
        $userId = auth()->id();

        $totalCreditValue = SalesRecord::where('agent_id', $userId)
            ->where('is_credit', true)
            ->where('status', 'approved')
            ->where('credit_status', 'pending_payment')
            ->sum('total_value');

        $pendingCount = SalesRecord::where('agent_id', $userId)
            ->where('is_credit', true)
            ->where('status', 'approved')
            ->where('credit_status', 'pending_payment')
            ->count();

        $overdueCount = SalesRecord::where('agent_id', $userId)
            ->where('is_credit', true)
            ->where('status', 'approved')
            ->where('credit_status', 'pending_payment')
            ->where('expected_collection_date', '<', now()->toDateString())
            ->count();

        return [
            Stat::make('Credit Sales Outstanding', '₦'.number_format($totalCreditValue))
                ->description("{$pendingCount} pending collection")
                ->descriptionIcon('heroicon-o-clock')
                ->color('warning'),
            Stat::make('Overdue Collections', number_format($overdueCount))
                ->description('Past expected date')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color($overdueCount > 0 ? 'danger' : 'success'),
        ];
    }
}
