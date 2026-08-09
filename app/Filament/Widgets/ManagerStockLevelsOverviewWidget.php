<?php

namespace App\Filament\Widgets;

use App\Models\AgentStock;
use App\Models\Inventory;
use Filament\Widgets\Widget;
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
        return auth()->user()->hasAnyRole(['admin', 'manager', 'general_manager']);
    }

    protected function makeAgentRow(AgentStock $item): object
    {
        $cartonQuantity = $item->productType?->cartonQuantityFor($item->grammage) ?? 1;

        return (object) [
            'location' => $item->agent?->name ?? 'Unknown Agent',
            'type' => 'Agent',
            'type_color' => 'success',
            'product' => $item->product_name,
            'grammage' => $item->grammage,
            'quantity' => $item->quantity,
            'carton_quantity' => $cartonQuantity,
            'cartons' => intdiv($item->quantity, $cartonQuantity),
            'remaining_pieces' => $item->quantity % $cartonQuantity,
        ];
    }

    public function getStockLevels(): array
    {
        $centralWarehouse = collect();
        $stateWarehouses = collect();

        $inventories = Inventory::with(['warehouse', 'productType'])
            ->where('quantity', '>', 0)
            ->get();

        foreach ($inventories as $inv) {
            $cartonQuantity = $inv->productType?->cartonQuantityFor($inv->grammage) ?? 1;

            $row = (object) [
                'location' => $inv->warehouse?->name ?? 'Unknown Warehouse',
                'type' => $inv->warehouse?->type === 'central' ? 'Central Warehouse' : 'State Warehouse',
                'type_color' => $inv->warehouse?->type === 'central' ? 'warning' : 'info',
                'product' => $inv->productType?->name ?? 'Unknown',
                'grammage' => $inv->grammage,
                'quantity' => $inv->quantity,
                'carton_quantity' => $cartonQuantity,
                'cartons' => intdiv($inv->quantity, $cartonQuantity),
                'remaining_pieces' => $inv->quantity % $cartonQuantity,
            ];

            if ($inv->warehouse?->type === 'central') {
                $centralWarehouse->push($row);
            } else {
                $stateWarehouses->push($row);
            }
        }

        $agentStocks = AgentStock::with('agent.state.region')
            ->where('quantity', '>', 0)
            ->get();

        $csrsByRegion = collect();
        $openRetailByRegion = collect();

        foreach ($agentStocks as $item) {
            $role = $item->agent?->role;
            $row = $this->makeAgentRow($item);

            $regionName = $item->agent?->state?->region?->name ?? 'Unknown Region';
            $stateName = $item->agent?->state?->name ?? 'Unknown State';

            if ($role === 'community_sales_representative') {
                $csrsByRegion->put($regionName, $csrsByRegion->get($regionName, collect()));
                $csrsByRegion[$regionName]->put($stateName, $csrsByRegion[$regionName]->get($stateName, collect()));
                $csrsByRegion[$regionName][$stateName]->push($row);
            } elseif (in_array($role, ['open_market', 'retail_market'])) {
                $openRetailByRegion->put($regionName, $openRetailByRegion->get($regionName, collect()));
                $openRetailByRegion[$regionName]->put($stateName, $openRetailByRegion[$regionName]->get($stateName, collect()));
                $openRetailByRegion[$regionName][$stateName]->push($row);
            } else {
                $csrsByRegion->put($regionName, $csrsByRegion->get($regionName, collect()));
                $csrsByRegion[$regionName]->put($stateName, $csrsByRegion[$regionName]->get($stateName, collect()));
                $csrsByRegion[$regionName][$stateName]->push($row);
            }
        }

        return [
            'Central Warehouse' => $centralWarehouse->sortByDesc('quantity')->values(),
            'State Warehouses' => $stateWarehouses->sortByDesc('quantity')->values(),
            'Community Sales Reps' => $csrsByRegion,
            'Open & Retail Market' => $openRetailByRegion,
        ];
    }
}
