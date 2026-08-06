<?php

namespace App\Livewire;

use App\Models\SalesRecord;
use App\Models\User;
use App\Support\DashboardDateScope;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class TeamSalesBreakdownTable extends Component
{
    use WithPagination;

    public ?int $selectedAgentId = null;

    public string $search = '';

    public function mount(): void
    {
        $user = auth()->user();
        if (! $user?->hasAnyRole(['rep', 'lead'])) {
            abort(403);
        }
    }

    public function updatedSearch(): void
    {
        $this->search = trim($this->search);
        $this->resetPage();
    }

    public function selectAgent(int $agentId): void
    {
        $this->selectedAgentId = $agentId;
        $this->search = '';
        $this->resetPage();
    }

    public function backToSummary(): void
    {
        $this->selectedAgentId = null;
        $this->search = '';
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, array{id: int, name: string, total: float, count: int}>
     */
    #[Computed]
    public function summaryRows(): LengthAwarePaginator
    {
        [$from, $to] = DashboardDateScope::fromSession();

        $csrs = $this->teamCsrs();

        $totals = SalesRecord::query()
            ->whereIn('agent_id', $csrs->pluck('id'))
            ->where('status', 'approved')
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('agent_id, COUNT(*) as total_count, SUM(total_value) as total_value')
            ->groupBy('agent_id')
            ->get()
            ->keyBy('agent_id');

        return $csrs
            ->map(function (User $csr) use ($totals): array {
                $total = $totals->get($csr->id);

                return [
                    'id' => $csr->id,
                    'name' => $csr->name,
                    'total' => (float) ($total?->total_value ?? 0),
                    'count' => (int) ($total?->total_count ?? 0),
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

        return $this->attachedCsrIds()->contains($this->selectedAgentId)
            ? User::find($this->selectedAgentId)
            : null;
    }

    /**
     * @return Collection<int, int>
     */
    private function attachedCsrIds(): Collection
    {
        return $this->teamCsrs()->pluck('id');
    }

    /**
     * @return Collection<int, User>
     */
    private function teamCsrs(): Collection
    {
        $user = auth()->user();

        if ($user->hasRole('lead')) {
            $repIds = User::where('lead_id', $user->id)->where('role', 'rep')->pluck('id');

            return User::where(function ($query) use ($repIds, $user) {
                $query->whereIn('portfolio_agent_id', $repIds)
                    ->orWhere('portfolio_agent_id', $user->id);
            })
                ->where('role', 'community_sales_representative')
                ->orderBy('name')
                ->get();
        }

        return User::where('portfolio_agent_id', $user->id)
            ->where('role', 'community_sales_representative')
            ->orderBy('name')
            ->get();
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

        $query = SalesRecord::query()
            ->where('agent_id', $this->selectedAgentId)
            ->where('status', 'approved')
            ->whereBetween('created_at', [$from, $to])
            ->latest('created_at');

        if ($this->search !== '') {
            $query->where(function ($q) {
                $search = '%'.strtolower($this->search).'%';
                $q->orWhereRaw('LOWER(customer_name) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(vendor_name) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(business_name) LIKE ?', [$search]);
            });
        }

        return $query->paginate(10);
    }

    public function render()
    {
        return view('livewire.team-sales-breakdown-table');
    }
}
