<?php

namespace App\Livewire;

use App\Models\SalesRecord;
use App\Models\User;
use App\Support\DashboardDateScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class OfficeSalesBreakdownTable extends Component
{
    use WithPagination;

    private const array ALLOWED_ROLES = [
        'sales',
        'manager',
        'admin',
        'general_manager',
        'accountant',
        'general_accountant',
    ];

    public ?int $selectedAgentId = null;

    public string $search = '';

    public string $statusFilter = '';

    public function mount(): void
    {
        $user = auth()->user();
        if (! $user?->hasAnyRole(self::ALLOWED_ROLES)) {
            abort(403);
        }
    }

    public function updatedSearch(): void
    {
        $this->search = trim($this->search);
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function selectAgent(int $agentId): void
    {
        if (! $this->allowedAgentIds()->contains($agentId)) {
            return;
        }

        $this->selectedAgentId = $agentId;
        $this->search = '';
        $this->statusFilter = '';
        $this->resetPage();
    }

    public function backToSummary(): void
    {
        $this->selectedAgentId = null;
        $this->search = '';
        $this->statusFilter = '';
        $this->resetPage();
    }

    public function statusOptions(): array
    {
        return [
            '' => 'All Statuses',
            'pending' => 'Pending Approval',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
        ];
    }

    /**
     * @return LengthAwarePaginator<int, array{id: int, name: string, approved: int, pending: int, rejected: int, total: float}>
     */
    #[Computed]
    public function summaryRows(): LengthAwarePaginator
    {
        [$from, $to] = DashboardDateScope::fromSession();

        $totals = $this->officeSalesQuery()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('agent_id, status, COUNT(*) as count, SUM(total_value) as total_value')
            ->groupBy('agent_id', 'status')
            ->get()
            ->groupBy('agent_id');

        return $this->agents()
            ->map(function (User $agent) use ($totals): array {
                $rows = $totals->get($agent->id, collect());

                return [
                    'id' => $agent->id,
                    'name' => $agent->name,
                    'approved' => (int) ($rows->firstWhere('status', 'approved')?->count ?? 0),
                    'pending' => (int) ($rows->firstWhere('status', 'pending')?->count ?? 0),
                    'rejected' => (int) ($rows->firstWhere('status', 'rejected')?->count ?? 0),
                    'total' => (float) ($rows->firstWhere('status', 'approved')?->total_value ?? 0),
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->when($this->search !== '', function (Collection $rows): Collection {
                $search = strtolower($this->search);

                return $rows->filter(fn (array $row): bool => str_contains(strtolower($row['name']), $search))
                    ->values();
            })
            ->pipe(function (Collection $rows): LengthAwarePaginator {
                $page = $this->getPage();

                return new LengthAwarePaginator(
                    $rows->forPage($page, 10)->values(),
                    $rows->count(),
                    10,
                    $page,
                    ['path' => LengthAwarePaginator::resolveCurrentPath()],
                );
            });
    }

    #[Computed]
    public function selectedAgent(): ?User
    {
        if ($this->selectedAgentId === null) {
            return null;
        }

        return $this->allowedAgentIds()->contains($this->selectedAgentId)
            ? User::find($this->selectedAgentId)
            : null;
    }

    /**
     * @return LengthAwarePaginator<int, SalesRecord>
     */
    #[Computed]
    public function detailRecords(): LengthAwarePaginator
    {
        if ($this->selectedAgentId === null) {
            return new LengthAwarePaginator([], 0, 10, $this->getPage(), ['path' => LengthAwarePaginator::resolveCurrentPath()]);
        }

        [$from, $to] = DashboardDateScope::fromSession();

        $query = $this->officeSalesQuery()
            ->where('agent_id', $this->selectedAgentId)
            ->whereBetween('created_at', [$from, $to])
            ->latest('created_at');

        if ($this->statusFilter !== '') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->search !== '') {
            $search = '%'.strtolower($this->search).'%';
            $query->where(function (Builder $q) use ($search) {
                $q->orWhereRaw('LOWER(customer_name) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(vendor_name) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(business_name) LIKE ?', [$search]);
            });
        }

        return $query->paginate(10);
    }

    /**
     * @return Collection<int, array{customer: ?string, product: string, grammage: string, quantity: float, price: float, line_total: float, status: string, date: Carbon}>
     */
    #[Computed]
    public function detailRows(): Collection
    {
        if ($this->selectedAgentId === null) {
            return collect();
        }

        return collect($this->detailRecords->items())
            ->flatMap(function (SalesRecord $record): array {
                $products = $record->products ?? [];

                if ($products === []) {
                    return [[
                        'customer' => $record->customer_name,
                        'product' => '-',
                        'grammage' => '-',
                        'quantity' => 0,
                        'price' => 0,
                        'line_total' => (float) $record->total_value,
                        'status' => $record->status,
                        'date' => $record->created_at,
                    ]];
                }

                return array_map(fn (array $product): array => [
                    'customer' => $record->customer_name,
                    'product' => $product['product_name'] ?? '-',
                    'grammage' => $product['grammage'] ?? '-',
                    'quantity' => (float) ($product['quantity'] ?? 0),
                    'price' => (float) ($product['price'] ?? 0),
                    'line_total' => (float) ($product['quantity'] ?? 0) * (float) ($product['price'] ?? 0),
                    'status' => $record->status,
                    'date' => $record->created_at,
                ], $products);
            })
            ->values();
    }

    /**
     * @return Collection<int, User>
     */
    private function agents(): Collection
    {
        if (auth()->user()->hasRole('sales')) {
            return collect([auth()->user()]);
        }

        return User::where('role', 'sales')->active()->orderBy('name')->get();
    }

    /**
     * @return Collection<int, int>
     */
    private function allowedAgentIds(): Collection
    {
        return $this->agents()->pluck('id');
    }

    private function officeSalesQuery(): Builder
    {
        return SalesRecord::query()
            ->where('agent_type', 'sales')
            ->whereIn('agent_id', $this->allowedAgentIds());
    }

    public function render()
    {
        return view('livewire.office-sales-breakdown-table');
    }
}
