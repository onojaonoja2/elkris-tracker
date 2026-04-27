<?php

namespace App\Filament\Resources\TrialOrders\Widgets;

use App\Models\Stockist;
use App\Models\StockistStock;
use App\Models\TrialOrder;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;

class SupervisorTrialStatsWidget extends BaseWidget
{
    #[Url]
    public ?string $state = null;

    protected function getStats(): array
    {
        $user = auth()->user();
        $state = $this->state;
        $stateFilter = $state ? strtolower(trim($state)) : null;

        if ($stateFilter) {
            $stockists = Stockist::where('supervisor_id', $user->id)
                ->where(DB::raw('LOWER(state)'), '=', $stateFilter)
                ->get();
        } else {
            $stockists = Stockist::where('supervisor_id', $user->id)->get();
        }

        $stockistIds = $stockists->pluck('id');
        $stats = [];

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
            ->selectRaw('product_name, grammage, SUM(quantity) as total_qty')
            ->groupBy('product_name', 'grammage')
            ->get();

        foreach ($byProduct as $product) {
            $stats[] = Stat::make("{$product->product_name} {$product->grammage}g", $product->total_qty.' units')
                ->description('Physical stock only')
                ->color('gray');
        }

        return $stats;
    }
}
