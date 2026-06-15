<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Stockists\StockistResource;
use App\Filament\Resources\StockistTransactions\StockistTransactionResource;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class StockistStatsWidget extends StatsOverviewWidget
{
    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    protected function getStats(): array
    {
        $user = auth()->user();
        $stockist = $user->stockist;

        if (! $stockist) {
            return [
                Stat::make('No Stockist Linked', '—')
                    ->description('Contact your supervisor')
                    ->icon('heroicon-o-exclamation-circle')
                    ->color('danger'),
            ];
        }

        $stockQty = $stockist->stocks()->sum('quantity');
        $transactionCount = $stockist->transactions()->count();

        return [
            Stat::make('Stockist', $stockist->name)
                ->description($stockist->city.', '.$stockist->state)
                ->icon('heroicon-o-building-storefront')
                ->color('info')
                ->url(StockistResource::getUrl('view', ['record' => $stockist])),
            Stat::make('Stock Quantity', number_format($stockQty))
                ->description('Current stock on hand')
                ->icon('heroicon-o-cube')
                ->color('success'),
            Stat::make('Transactions', $transactionCount)
                ->description('Total transactions')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->url(StockistTransactionResource::getUrl('index')),
        ];
    }

    public static function canView(): bool
    {
        return auth()->user()->role === 'stockist';
    }
}
