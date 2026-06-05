<?php

namespace App\Filament\Widgets;

use App\Models\Inventory;
use App\Models\StockTransaction;
use App\Models\StockTransfer;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class WarehouseManagerStatsWidget extends StatsOverviewWidget
{
    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    protected function getStats(): array
    {
        $user = auth()->user();
        $warehouseIds = $user->managedWarehouses()->pluck('id');

        $totalInventory = Inventory::whereIn('warehouse_id', $warehouseIds)->sum('quantity');
        $pendingDispatches = StockTransfer::whereIn('from_warehouse_id', $warehouseIds)
            ->where('status', 'approved')
            ->count();
        $recentReceives = StockTransaction::where('user_id', $user->id)
            ->where('type', 'received')
            ->whereDate('created_at', today())
            ->count();

        return [
            Stat::make('Total Stock', number_format($totalInventory))
                ->description('Units in your warehouses')
                ->icon('heroicon-o-cube')
                ->color('info'),
            Stat::make('Pending Dispatch', $pendingDispatches)
                ->description('Approved transfers waiting')
                ->icon('heroicon-o-truck')
                ->color('warning'),
            Stat::make('Today\'s Receives', $recentReceives)
                ->description('Items received today')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success'),
        ];
    }

    public static function canView(): bool
    {
        return auth()->user()->role === 'warehouse_manager';
    }
}
