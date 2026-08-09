<?php

namespace App\Filament\Widgets;

use App\Models\AgentStock;
use App\Models\Inventory;
use App\Models\ProductType;
use Filament\Widgets\Widget;
use Livewire\Attributes\On;

class AccountantStockLevelsWidget extends Widget
{
    protected string $view = 'filament.widgets.stock-levels';

    public string $search = '';

    #[On('refresh-dashboard')]
    public function refreshWidget(): void {}

    public function updatedSearch(): void {}

    public static function canView(): bool
    {
        return auth()->user()->hasAnyRole(['accountant', 'general_accountant']);
    }

    protected function getCartonQuantity(?int $productTypeId, int $grammage): int
    {
        if (! $productTypeId) {
            return 1;
        }

        $productType = ProductType::find($productTypeId);
        if (! $productType) {
            return 1;
        }

        foreach ($productType->available_grammages as $entry) {
            if (is_array($entry) && ($entry['grammage'] ?? null) == $grammage) {
                return $entry['carton_quantity'] ?? 1;
            }
        }

        return 1;
    }

    protected function makeRow(array $data): object
    {
        $cartonQty = $this->getCartonQuantity($data['product_type_id'], $data['grammage']);
        $cartons = intdiv($data['quantity'], $cartonQty);
        $remaining = $data['quantity'] % $cartonQty;

        return (object) [
            'location' => $data['location'],
            'type' => $data['type'],
            'type_color' => $data['type_color'],
            'product' => $data['product'],
            'grammage' => $data['grammage'],
            'quantity' => $data['quantity'],
            'carton_quantity' => $cartonQty,
            'cartons' => $cartons,
            'remaining_pieces' => $remaining,
            'agent_id' => $data['agent_id'] ?? null,
            'warehouse_id' => $data['warehouse_id'] ?? null,
        ];
    }

    protected function makeAgentRow(AgentStock $item): object
    {
        return $this->makeRow([
            'location' => $item->agent?->name ?? 'Unknown Agent',
            'type' => 'Agent',
            'type_color' => 'success',
            'product' => $item->product_name,
            'product_type_id' => $item->product_type_id,
            'grammage' => $item->grammage,
            'quantity' => $item->quantity,
            'agent_id' => $item->user_id,
        ]);
    }

    protected function matchesSearch(object $row): bool
    {
        if ($this->search === '') {
            return true;
        }

        $search = strtolower($this->search);

        return str_contains(strtolower($row->location), $search)
            || str_contains(strtolower($row->product), $search)
            || str_contains(strtolower($row->type), $search)
            || str_contains((string) $row->grammage, $search);
    }

    public function getStockLevels(): array
    {
        $centralWarehouse = collect();
        $stateWarehouses = collect();

        $inventories = Inventory::with(['warehouse', 'productType'])
            ->where('quantity', '>', 0)
            ->get();

        foreach ($inventories as $inv) {
            $row = $this->makeRow([
                'location' => $inv->warehouse?->name ?? 'Unknown Warehouse',
                'type' => $inv->warehouse?->type === 'central' ? 'Central Warehouse' : 'State Warehouse',
                'type_color' => $inv->warehouse?->type === 'central' ? 'warning' : 'info',
                'product' => $inv->productType?->name ?? 'Unknown',
                'product_type_id' => $inv->product_type_id,
                'grammage' => $inv->grammage,
                'quantity' => $inv->quantity,
                'warehouse_id' => $inv->warehouse_id,
            ]);

            if (! $this->matchesSearch($row)) {
                continue;
            }

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

            if (! $this->matchesSearch($row)) {
                continue;
            }

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
