<?php

namespace App\Filament\Widgets;

use App\Models\AgentStock;
use App\Models\Inventory;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;

class AccountantStockLevelsWidget extends Widget
{
    protected string $view = 'filament.widgets.stock-levels';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public static function canView(): bool
    {
        return auth()->user()->role === 'accountant';
    }

    public function getStockLevels(): Collection
    {
        $rows = collect();

        $inventories = Inventory::with(['warehouse', 'productType'])->where('quantity', '>', 0)->get();
        foreach ($inventories as $inv) {
            $rows->push((object) [
                'location' => $inv->warehouse?->name ?? 'Unknown Warehouse',
                'type' => $inv->warehouse?->type === 'central' ? 'Central Warehouse' : 'State Warehouse',
                'type_color' => $inv->warehouse?->type === 'central' ? 'warning' : 'info',
                'product' => $inv->productType?->name ?? 'Unknown',
                'grammage' => $inv->grammage,
                'quantity' => $inv->quantity,
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
