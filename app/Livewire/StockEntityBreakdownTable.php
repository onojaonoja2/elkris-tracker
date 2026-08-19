<?php

namespace App\Livewire;

use App\Models\AgentStock;
use App\Models\DamagedStockReturn;
use App\Models\SalesRecord;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\DashboardDateScope;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class StockEntityBreakdownTable extends Component
{
    use WithPagination;

    private const array ALLOWED_ROLES = [
        'accountant',
        'general_accountant',
    ];

    private const array MOVED_TRANSFER_STATUSES = [
        'dispatched',
        'received',
        'collected',
    ];

    public string $search = '';

    public string $typeFilter = 'all';

    public function mount(): void
    {
        if (! auth()->user()?->hasAnyRole(self::ALLOWED_ROLES)) {
            abort(403);
        }
    }

    public function updatedSearch(): void
    {
        $this->search = trim($this->search);
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    /**
     * @return Collection<int, object{id: int, name: string, type: string, type_color: string, in_total: int, out_total: int, net: int}>
     */
    #[Computed]
    public function entities(): Collection
    {
        [$from, $to] = DashboardDateScope::fromSession();

        $agentRows = $this->agents()->map(
            fn (object $row): object => (object) [
                'id' => $row->id,
                'name' => $row->name,
                'type' => 'Agent',
                'type_color' => 'success',
                'in_total' => $row->in_total,
                'out_total' => $row->out_total,
                'net' => $row->in_total - $row->out_total,
            ]
        );

        $warehouseRows = $this->warehouses()->map(
            fn (object $row): object => (object) [
                'id' => $row->id,
                'name' => $row->name,
                'type' => 'Warehouse',
                'type_color' => 'warning',
                'in_total' => $row->in_total,
                'out_total' => $row->out_total,
                'net' => $row->in_total - $row->out_total,
            ]
        );

        return $agentRows
            ->concat($warehouseRows)
            ->filter(fn (object $row): bool => $this->matchesRow($row))
            ->sortByDesc('net')
            ->values();
    }

    /**
     * @return LengthAwarePaginator<int, object{id: int, name: string, type: string, type_color: string, in_total: int, out_total: int, net: int}>
     */
    #[Computed]
    public function rows(): LengthAwarePaginator
    {
        $page = $this->getPage();
        $entities = $this->entities;

        return new LengthAwarePaginator(
            $entities->forPage($page, 15)->values(),
            $entities->count(),
            15,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()],
        );
    }

    /**
     * @return Collection<int, object{id: int, name: string, in_total: int, out_total: int}>
     */
    private function agents(): Collection
    {
        [$from, $to] = DashboardDateScope::fromSession();

        $agentIds = AgentStock::where('quantity', '>', 0)->distinct()->pluck('user_id');

        $inByAgent = $this->transferAggregates('to_agent_id', $from, $to);
        $outByAgent = $this->transferAggregates('from_agent_id', $from, $to);
        $damagedOutByAgent = DamagedStockReturn::where('status', 'approved')
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('user_id', $agentIds)
            ->selectRaw('user_id as entity_id, SUM(quantity) as total')
            ->groupBy('user_id')
            ->pluck('total', 'entity_id');
        $salesOutByAgent = $this->salesAggregates('agent_id', $from, $to);

        return User::whereIn('id', $agentIds)
            ->orderBy('name')
            ->get()
            ->map(function (User $user) use ($inByAgent, $outByAgent, $damagedOutByAgent, $salesOutByAgent): object {
                $in = (int) ($inByAgent->get($user->id) ?? 0);
                $out = (int) ($outByAgent->get($user->id) ?? 0)
                    + (int) ($damagedOutByAgent->get($user->id) ?? 0)
                    + (int) ($salesOutByAgent->get($user->id) ?? 0);

                return (object) [
                    'id' => $user->id,
                    'name' => $user->name,
                    'in_total' => $in,
                    'out_total' => $out,
                ];
            })
            ->values();
    }

    /**
     * @return Collection<int, object{id: int, name: string, in_total: int, out_total: int}>
     */
    private function warehouses(): Collection
    {
        [$from, $to] = DashboardDateScope::fromSession();

        $inByWarehouse = $this->transferAggregates('to_warehouse_id', $from, $to);
        $outByWarehouse = $this->transferAggregates('from_warehouse_id', $from, $to);
        $damagedOutByWarehouse = DamagedStockReturn::where('status', 'approved')
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('warehouse_id as entity_id, SUM(quantity) as total')
            ->whereNotNull('warehouse_id')
            ->groupBy('warehouse_id')
            ->pluck('total', 'entity_id');
        $salesOutByWarehouse = $this->salesAggregates('warehouse_id', $from, $to);

        return Warehouse::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function (Warehouse $warehouse) use ($inByWarehouse, $outByWarehouse, $damagedOutByWarehouse, $salesOutByWarehouse): object {
                $in = (int) ($inByWarehouse->get($warehouse->id) ?? 0);
                $out = (int) ($outByWarehouse->get($warehouse->id) ?? 0)
                    + (int) ($damagedOutByWarehouse->get($warehouse->id) ?? 0)
                    + (int) ($salesOutByWarehouse->get($warehouse->id) ?? 0);

                return (object) [
                    'id' => $warehouse->id,
                    'name' => $warehouse->name,
                    'in_total' => $in,
                    'out_total' => $out,
                ];
            })
            ->values();
    }

    /**
     * @return Collection<int, int>
     */
    private function transferAggregates(string $column, Carbon $from, Carbon $to): Collection
    {
        return StockTransfer::whereIn('status', self::MOVED_TRANSFER_STATUSES)
            ->whereNotNull($column)
            ->whereBetween('stock_transfers.created_at', [$from, $to])
            ->join('stock_transfer_items', 'stock_transfers.id', '=', 'stock_transfer_items.stock_transfer_id')
            ->selectRaw("{$column} as entity_id, SUM(stock_transfer_items.quantity) as total")
            ->groupBy($column)
            ->pluck('total', 'entity_id');
    }

    /**
     * @return Collection<int, int>
     */
    private function salesAggregates(string $column, Carbon $from, Carbon $to): Collection
    {
        $query = SalesRecord::whereIn('status', ['pending', 'receipt_uploaded', 'approved'])
            ->whereBetween('created_at', [$from, $to]);

        if ($column === 'agent_id') {
            $query->where('stock_source', 'held');
        } else {
            $query->whereNotNull('warehouse_id');
        }

        $totals = [];

        $query->get([$column, 'products'])->each(function (SalesRecord $sale) use (&$totals, $column): void {
            $entityId = $sale->{$column};
            if ($entityId === null) {
                return;
            }

            $quantity = collect($sale->products ?? [])
                ->filter(fn (mixed $product): bool => is_array($product))
                ->sum(fn (array $product): int => (int) ($product['quantity'] ?? 0));

            $totals[$entityId] = ($totals[$entityId] ?? 0) + $quantity;
        });

        return collect($totals);
    }

    private function matchesRow(object $row): bool
    {
        if ($this->typeFilter === 'agent' && $row->type !== 'Agent') {
            return false;
        }

        if ($this->typeFilter === 'warehouse' && $row->type !== 'Warehouse') {
            return false;
        }

        if ($this->search === '') {
            return true;
        }

        $search = strtolower($this->search);

        return str_contains(strtolower($row->name), $search)
            || str_contains(strtolower($row->type), $search);
    }

    public function render()
    {
        return view('livewire.stock-entity-breakdown-table');
    }
}
