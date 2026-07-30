<?php

namespace App\Filament\Widgets;

use App\Models\SalesRecord;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class CreditSalesOutstandingStatsWidget extends BaseWidget
{
    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return auth()->user()->hasAnyRole([
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

        $baseQuery = SalesRecord::where('is_credit', true)
            ->where('status', 'approved')
            ->where('credit_status', 'pending_payment');

        if (in_array($role, ['community_sales_representative', 'open_market', 'retail_market'], true)) {
            $value = (clone $baseQuery)->where('agent_id', $user->id)->sum('total_value');
            $overdueCount = (clone $baseQuery)
                ->where('agent_id', $user->id)
                ->where('expected_collection_date', '<', now()->toDateString())
                ->count();

            return [
                Stat::make('My Credit Sales Outstanding', '₦'.number_format($value))
                    ->description('Pending collection')
                    ->icon('heroicon-o-clock')
                    ->color('warning')
                    ->extraAttributes(['class' => 'cursor-pointer', 'wire:click' => "\$dispatch('open-credit-breakdown', { category: 'my' })"]),
                Stat::make('Overdue Collections', number_format($overdueCount))
                    ->description('Past expected date')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color($overdueCount > 0 ? 'danger' : 'success')
                    ->extraAttributes(['class' => 'cursor-pointer', 'wire:click' => "\$dispatch('open-credit-breakdown', { category: 'overdue' })"]),
            ];
        }

        if ($role === 'supervisor') {
            $csrIds = User::where('role', 'community_sales_representative')->active()->pluck('id');
            $total = (clone $baseQuery)->whereIn('agent_id', $csrIds)->sum('total_value');
            $csrValue = (clone $baseQuery)->whereIn('agent_id', $csrIds)->sum('total_value');

            return [
                Stat::make('Total Credit Outstanding', '₦'.number_format($total))
                    ->description('All CSRs under supervision')
                    ->icon('heroicon-o-banknotes')
                    ->color('warning')
                    ->extraAttributes(['class' => 'cursor-pointer', 'wire:click' => "\$dispatch('open-credit-breakdown', { category: 'csr' })"]),
                Stat::make('CSR Credit Outstanding', '₦'.number_format($csrValue))
                    ->description('Community sales representatives')
                    ->icon('heroicon-o-users')
                    ->color('warning')
                    ->extraAttributes(['class' => 'cursor-pointer', 'wire:click' => "\$dispatch('open-credit-breakdown', { category: 'community_sales_representative' })"]),
            ];
        }

        $total = (clone $baseQuery)->sum('total_value');

        $stats = [
            Stat::make('Total Credit Sales Outstanding', '₦'.number_format($total))
                ->description('Across all agents')
                ->icon('heroicon-o-banknotes')
                ->color('warning')
                ->extraAttributes(['class' => 'cursor-pointer', 'wire:click' => "\$dispatch('open-credit-breakdown', { category: 'total' })"]),
        ];

        $categories = [
            ['label' => 'CSR Credit Outstanding', 'role' => 'community_sales_representative', 'icon' => 'heroicon-o-users'],
            ['label' => 'Open Market Credit Outstanding', 'role' => 'open_market', 'icon' => 'heroicon-o-shopping-bag'],
            ['label' => 'Retail Market Credit Outstanding', 'role' => 'retail_market', 'icon' => 'heroicon-o-building-storefront'],
        ];

        foreach ($categories as $category) {
            $value = (clone $baseQuery)
                ->where('agent_type', $category['role'])
                ->sum('total_value');

            $stats[] = Stat::make($category['label'], '₦'.number_format($value))
                ->description('Pending collection')
                ->icon($category['icon'])
                ->color('warning')
                ->extraAttributes(['class' => 'cursor-pointer', 'wire:click' => "\$dispatch('open-credit-breakdown', { category: '{$category['role']}' })"]);
        }

        return $stats;
    }
}
