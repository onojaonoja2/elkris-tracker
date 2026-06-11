<?php

namespace App\Filament\Widgets;

use App\Models\AgentStock;
use App\Models\Inventory;
use App\Models\StockistStock;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;

class ManagerStockLevelsOverviewWidget extends Widget
{
    protected static ?string $heading = 'Stock Levels Overview';

    protected string $view = 'filament.widgets.manager-stock-levels';

    protected int|string|array $columnSpan = 'full';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return auth()->user()->role === 'manager' || auth()->user()->role === 'admin';
    }

    public function getStockLevels(): Collection
    {
        $rows = collect();

        $inventories = Inventory::with(['warehouse', 'productType'])
            ->where('quantity', '>', 0)
            ->get();

        foreach ($inventories as $inv) {
            $rows->push((object) [
                'location' => $inv->warehouse?->name ?? 'Unknown Warehouse',
                'type' => 'Warehouse',
                'type_color' => $inv->warehouse?->type === 'central' ? 'warning' : 'info',
                'product' => $inv->productType?->name ?? 'Unknown',
                'grammage' => $inv->grammage,
                'quantity' => $inv->quantity,
            ]);
        }

        $stockistStocks = StockistStock::with('stockist')
            ->where('quantity', '>', 0)
            ->get();

        foreach ($stockistStocks as $item) {
            $rows->push((object) [
                'location' => $item->stockist?->name ?? 'Unknown Stockist',
                'type' => 'Stockist',
                'type_color' => 'primary',
                'product' => $item->product_name,
                'grammage' => $item->grammage,
                'quantity' => $item->quantity,
            ]);
        }

        $agentStocks = AgentStock::with('agent')
            ->where('quantity', '>', 0)
            ->get();

        foreach ($agentStocks as $item) {
            $rows->push((object) [
                'location' => $item->agent?->name ?? 'Unknown Agent',
                'type' => 'Agent',
                'type_color' => 'success',
                'product' => $item->product_name,
                'grammage' => $item->grammage,
                'quantity' => $item->quantity,
            ]);
        }

        return $rows->sortByDesc('quantity');
    }
}
