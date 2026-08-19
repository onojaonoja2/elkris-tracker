<?php

namespace App\Livewire;

use App\Models\SalesRecord;
use App\Models\User;
use App\Support\DashboardDateScope;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class RevenueBreakdownTable extends Component
{
    use WithPagination;

    public ?int $agentId = null;

    public string $search = '';

    public function mount(?int $agentId = null): void
    {
        $this->agentId = $agentId;
    }

    public function selectAgent(int $agentId): void
    {
        $this->agentId = $agentId;
        $this->resetPage();
    }

    public function back(): void
    {
        $this->agentId = null;
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function scope(): array
    {
        if (auth()->user()?->hasRole('supervisor')) {
            $from = Session::get('supervisor_date_from', now()->startOfDay()->toDateTimeString());
            $to = Session::get('supervisor_date_to', now()->endOfDay()->toDateTimeString());

            return [$from, $to];
        }

        $scope = DashboardDateScope::fromSession();

        return [$scope[0]->toDateTimeString(), $scope[1]->toDateTimeString()];
    }

    /**
     * @return array<int, string>
     */
    private function sellingRoles(): array
    {
        if (auth()->user()?->hasRole('supervisor')) {
            return ['community_sales_representative'];
        }

        return [
            'field_agent',
            'community_sales_representative',
            'open_market',
            'retail_market',
            'rep',
            'lead',
        ];
    }

    /**
     * @return Collection<int, object{id: int, name: string, lga: ?string, state: ?string, sales_count: int, sales_value: float, pending_count: int, revenue: float}>
     */
    #[Computed]
    public function agents(): Collection
    {
        [$from, $to] = $this->scope();

        $agentIds = User::whereIn('role', $this->sellingRoles())->active()->pluck('id');

        $salesAgg = SalesRecord::whereIn('agent_id', $agentIds)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('agent_id, COUNT(*) as sales_count, COALESCE(SUM(total_value), 0) as sales_value')
            ->groupBy('agent_id')
            ->get()
            ->keyBy('agent_id');

        $pendingAgg = SalesRecord::whereIn('agent_id', $agentIds)
            ->whereIn('status', ['pending', 'receipt_uploaded'])
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('agent_id, COUNT(*) as pending_count')
            ->groupBy('agent_id')
            ->get()
            ->keyBy('agent_id');

        $revenue = SalesRecord::revenueByAgent($agentIds->all(), Carbon::parse($from), Carbon::parse($to));

        return User::whereIn('id', $agentIds)
            ->with(['lga', 'state'])
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user): bool => blank($this->search)
                || str_contains(strtolower($user->name), strtolower($this->search)))
            ->map(fn (User $user) => (object) [
                'id' => $user->id,
                'name' => $user->name,
                'lga' => $user->lga?->name,
                'state' => $user->state?->name,
                'sales_count' => (int) ($salesAgg->get($user->id)?->sales_count ?? 0),
                'sales_value' => (float) ($salesAgg->get($user->id)?->sales_value ?? 0),
                'pending_count' => (int) ($pendingAgg->get($user->id)?->pending_count ?? 0),
                'revenue' => (float) ($revenue->get($user->id)?->revenue ?? 0),
            ]);
    }

    #[Computed]
    public function selectedAgent(): ?User
    {
        return $this->agentId ? User::find($this->agentId) : null;
    }

    #[Computed]
    public function agentRecords(): LengthAwarePaginator
    {
        [$from, $to] = $this->scope();

        return SalesRecord::where('agent_id', $this->agentId)
            ->whereBetween('created_at', [$from, $to])
            ->with('agent')
            ->latest('created_at')
            ->paginate(10);
    }

    public function render()
    {
        return view('livewire.revenue-breakdown-table');
    }
}
