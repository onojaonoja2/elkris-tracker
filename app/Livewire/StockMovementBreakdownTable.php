<?php

namespace App\Livewire;

use App\Models\DamagedStockReturn;
use App\Models\SalesRecord;
use App\Models\StockCount;
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

class StockMovementBreakdownTable extends Component
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

    public string $entityType;

    public int $entityId;

    public ?string $product = null;

    public ?int $grammage = null;

    public string $typeFilter = 'all';

    public string $search = '';

    public function mount(string $entityType, int $entityId, ?string $product = null, ?int $grammage = null): void
    {
        $this->entityType = $entityType;
        $this->entityId = $entityId;
        $this->product = $product;
        $this->grammage = $grammage;

        if (! auth()->user()?->hasAnyRole(self::ALLOWED_ROLES)) {
            abort(403);
        }
    }

    public function clearProductFilter(): void
    {
        $this->product = null;
        $this->grammage = null;
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function entity(): User|Warehouse|null
    {
        return $this->entityType === 'agent'
            ? User::find($this->entityId)
            : Warehouse::find($this->entityId);
    }

    /**
     * @return Collection<int, object{date: Carbon, type: string, direction: string, quantity: int, product: string, grammage: ?int, reference: string, status: string, details: string}>
     */
    #[Computed]
    public function movements(): Collection
    {
        return collect()
            ->merge($this->transferLines('in'))
            ->merge($this->transferLines('out'))
            ->merge($this->stockCountLines())
            ->merge($this->damagedReturnLines())
            ->merge($this->salesLines())
            ->filter(fn (object $row): bool => $this->matchesRow($row))
            ->sortByDesc(fn (object $row): string => $row->date->toDateTimeString())
            ->values();
    }

    /**
     * @return LengthAwarePaginator<int, object{date: Carbon, type: string, direction: string, quantity: int, product: string, grammage: ?int, reference: string, status: string, details: string}>
     */
    #[Computed]
    public function rows(): LengthAwarePaginator
    {
        $page = $this->getPage();
        $rows = $this->movements;

        return new LengthAwarePaginator(
            $rows->forPage($page, 15)->values(),
            $rows->count(),
            15,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()],
        );
    }

    #[Computed]
    public function totals(): array
    {
        $rows = $this->movements;

        return [
            'in' => $rows->where('direction', 'in')->sum('quantity'),
            'out' => $rows->where('direction', 'out')->sum('quantity'),
            'net' => $rows->where('direction', 'in')->sum('quantity') - $rows->where('direction', 'out')->sum('quantity'),
        ];
    }

    /**
     * @return Collection<int, object{date: Carbon, type: string, direction: string, quantity: int, product: string, grammage: ?int, reference: string, status: string, details: string}>
     */
    private function transferLines(string $direction): Collection
    {
        $query = StockTransfer::whereIn('status', self::MOVED_TRANSFER_STATUSES)
            ->with('items.productType');

        if ($this->entityType === 'agent') {
            $query->where($direction === 'in' ? 'to_agent_id' : 'from_agent_id', $this->entityId);
        } else {
            $query->where($direction === 'in' ? 'to_warehouse_id' : 'from_warehouse_id', $this->entityId);
        }

        $typeLabel = $direction === 'in' ? 'Transfer In' : 'Transfer Out';

        return $query->get()->flatMap(function (StockTransfer $transfer) use ($direction, $typeLabel): array {
            return $transfer->items->map(function ($item) use ($transfer, $direction, $typeLabel): object {
                return (object) [
                    'date' => $transfer->received_at ?? $transfer->updated_at,
                    'type' => $typeLabel,
                    'direction' => $direction,
                    'quantity' => (int) $item->quantity,
                    'product' => $item->productType?->name ?? 'Unknown',
                    'grammage' => $item->grammage,
                    'reference' => '#'.$transfer->id,
                    'status' => (string) $transfer->status->value,
                    'details' => $this->transferDetails($transfer),
                ];
            })->all();
        });
    }

    private function transferDetails(StockTransfer $transfer): string
    {
        $from = $transfer->fromWarehouse?->name
            ?? $transfer->fromAgent?->name
            ?? 'Unknown';

        $to = $transfer->toWarehouse?->name
            ?? $transfer->toAgent?->name
            ?? 'Unknown';

        return "{$from} → {$to}";
    }

    /**
     * @return Collection<int, object{date: Carbon, type: string, direction: string, quantity: int, product: string, grammage: ?int, reference: string, status: string, details: string}>
     */
    private function stockCountLines(): Collection
    {
        $query = StockCount::where('status', '!=', 'rejected')
            ->with('items');

        if ($this->entityType === 'agent') {
            $query->where('user_id', $this->entityId);
        } else {
            $query->where('warehouse_id', $this->entityId);
        }

        return $query->get()->flatMap(function (StockCount $count): array {
            return $count->items->map(function ($item) use ($count): object {
                return (object) [
                    'date' => $count->approved_at ?? $count->updated_at,
                    'type' => 'Stock Count',
                    'direction' => 'neutral',
                    'quantity' => (int) $item->quantity,
                    'product' => $item->product_name ?? 'Unknown',
                    'grammage' => $item->grammage,
                    'reference' => '#'.$count->id,
                    'status' => (string) $count->status,
                    'details' => $count->is_additional_count ? 'Additional count' : 'Initial count',
                ];
            })->all();
        });
    }

    /**
     * @return Collection<int, object{date: Carbon, type: string, direction: string, quantity: int, product: string, grammage: ?int, reference: string, status: string, details: string}>
     */
    private function damagedReturnLines(): Collection
    {
        $query = DamagedStockReturn::where('status', 'approved')
            ->with('productType');

        if ($this->entityType === 'agent') {
            $query->where('user_id', $this->entityId);
        } else {
            $query->where('warehouse_id', $this->entityId);
        }

        return $query->get()->map(function (DamagedStockReturn $return): object {
            return (object) [
                'date' => $return->updated_at,
                'type' => 'Damaged Return',
                'direction' => 'out',
                'quantity' => (int) $return->quantity,
                'product' => $return->productType?->name ?? 'Unknown',
                'grammage' => $return->grammage,
                'reference' => '#'.$return->id,
                'status' => (string) $return->status,
                'details' => $return->reason ?? 'Damaged stock',
            ];
        });
    }

    /**
     * @return Collection<int, object{date: Carbon, type: string, direction: string, quantity: int, product: string, grammage: ?int, reference: string, status: string, details: string}>
     */
    private function salesLines(): Collection
    {
        $query = SalesRecord::whereIn('status', ['pending', 'receipt_uploaded', 'approved']);

        if ($this->entityType === 'agent') {
            $query->where('agent_id', $this->entityId)
                ->where('stock_source', 'held');
        } else {
            $query->where('warehouse_id', $this->entityId);
        }

        return $query->get()->flatMap(function (SalesRecord $sale): array {
            $products = collect($sale->products ?? [])
                ->filter(fn (mixed $product): bool => is_array($product));

            return $products->map(function (array $product) use ($sale): object {
                return (object) [
                    'date' => $sale->created_at,
                    'type' => 'Sale',
                    'direction' => 'out',
                    'quantity' => (int) ($product['quantity'] ?? 0),
                    'product' => $product['product_name'] ?? 'Unknown',
                    'grammage' => isset($product['grammage']) ? (int) $product['grammage'] : null,
                    'reference' => '#'.$sale->id,
                    'status' => (string) $sale->status,
                    'details' => $sale->customer_name ?? 'Sale',
                ];
            })->all();
        });
    }

    private function matchesRow(object $row): bool
    {
        if ($this->product !== null && strtolower($row->product) !== strtolower($this->product)) {
            return false;
        }

        if ($this->grammage !== null && (int) $row->grammage !== $this->grammage) {
            return false;
        }

        if ($this->typeFilter === 'in' && $row->direction !== 'in') {
            return false;
        }

        if ($this->typeFilter === 'out' && $row->direction !== 'out') {
            return false;
        }

        if ($this->typeFilter === 'count' && $row->type !== 'Stock Count') {
            return false;
        }

        [$from, $to] = DashboardDateScope::fromSession();
        if (! $row->date->between($from, $to)) {
            return false;
        }

        if ($this->search === '') {
            return true;
        }

        $search = strtolower($this->search);

        return str_contains(strtolower($row->product), $search)
            || str_contains(strtolower($row->reference), $search)
            || str_contains(strtolower($row->type), $search)
            || str_contains(strtolower($row->details), $search);
    }

    public function render()
    {
        return view('livewire.stock-movement-breakdown-table');
    }
}
