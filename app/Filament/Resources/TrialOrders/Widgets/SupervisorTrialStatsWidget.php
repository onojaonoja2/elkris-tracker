<?php

namespace App\Filament\Resources\TrialOrders\Widgets;

use App\Models\Stockist;
use App\Models\StockistStock;
use App\Models\TrialOrder;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class SupervisorTrialStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $user = auth()->user();
        $stats = [];

        $state = request()->get('state');

        if ($state) {
            $stockists = Stockist::where('supervisor_id', $user->id)
                ->where('state', $state)
                ->get();
        } else {
            $stockists = Stockist::where('supervisor_id', $user->id)->get();
        }

        $stockistIds = $stockists->pluck('id');

        if ($stockistIds->isEmpty()) {
            return [
                Stat::make('Total Stock Value', '₦0')
                    ->description($state ? "Stockists in {$state}" : 'All stockists')
                    ->icon('heroicon-o-currency-dollar')
                    ->color('success'),
                Stat::make('Total Units', '0')
                    ->description($state ? "Products in {$state}" : 'All products')
                    ->icon('heroicon-o-cube')
                    ->color('info'),
                Stat::make('Pending Orders', '0')
                    ->description('Awaiting approval')
                    ->icon('heroicon-o-clock')
                    ->color('warning'),
            ];
        }

        $totalValue = $stockists->sum('stock_balance');
        $stats[] = Stat::make('Total Stock Value', '₦'.number_format($totalValue, 0))
            ->description($state ? "Stockists in {$state}" : 'All stockists')
            ->icon('heroicon-o-currency-dollar')
            ->color('success');

        $totalUnits = StockistStock::whereIn('stockist_id', $stockistIds)->sum('quantity');
        $stats[] = Stat::make('Total Units', number_format($totalUnits))
            ->description($state ? "Products in {$state}" : 'All products')
            ->icon('heroicon-o-cube')
            ->color('info');

        $pendingOrders = TrialOrder::where('status', 'pending')
            ->whereHas('agent', fn ($q) => $q->whereJsonContains('assigned_cities', fn ($cityQuery) => $cityQuery
                ->select('city')
                ->from('stockists')
                ->whereColumn('stockists.city', DB::raw('ANY(users.assigned_cities)'))
                ->whereIn('stockists.id', $stockistIds)))
            ->count();
        $stats[] = Stat::make('Pending Orders', $pendingOrders)
            ->description('Awaiting approval')
            ->icon('heroicon-o-clock')
            ->color('warning');

        $byProduct = StockistStock::whereIn('stockist_id', $stockistIds)
            ->selectRaw('product_name, SUM(quantity) as total_qty, SUM(quantity * unit_price) as total_value')
            ->groupBy('product_name')
            ->get();

        foreach ($byProduct as $product) {
            $stats[] = Stat::make($product->product_name, $product->total_qty.' units')
                ->description('₦'.number_format($product->total_value, 0))
                ->color('gray');
        }

        return $stats;
    }
}
