<?php

namespace App\Livewire;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Support\DashboardDateScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class CsrOrderBreakdownTable extends Component
{
    use WithPagination;

    private const array ALLOWED_ROLES = [
        'supervisor',
        'manager',
        'accountant',
        'general_accountant',
        'general_manager',
        'admin',
    ];

    public ?int $selectedCsrId = null;

    public string $search = '';

    public function mount(): void
    {
        $user = auth()->user();
        if (! $user?->hasAnyRole(self::ALLOWED_ROLES)) {
            abort(403);
        }
    }

    public function selectCsr(int $csrId): void
    {
        if (! $this->csrs()->pluck('id')->contains($csrId)) {
            return;
        }

        $this->selectedCsrId = $csrId;
        $this->search = '';
        $this->resetPage();
    }

    public function backToSummary(): void
    {
        $this->selectedCsrId = null;
        $this->search = '';
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, array{id: int, name: string, completed: int}>
     */
    #[Computed]
    public function summaryRows(): LengthAwarePaginator
    {
        [$from, $to] = DashboardDateScope::fromSession();

        $counts = Order::query()
            ->where('is_migrated_order', false)
            ->whereBetween('created_at', [$from, $to])
            ->where('status', OrderStatus::Delivered)
            ->whereIn('assigned_to', $this->csrs()->pluck('id'))
            ->selectRaw('assigned_to, COUNT(*) as completed')
            ->groupBy('assigned_to')
            ->pluck('completed', 'assigned_to');

        return $this->csrs()
            ->map(fn (User $csr): array => [
                'id' => $csr->id,
                'name' => $csr->name,
                'completed' => (int) ($counts->get($csr->id) ?? 0),
            ])
            ->sortByDesc('completed')
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
    public function selectedCsr(): ?User
    {
        if ($this->selectedCsrId === null) {
            return null;
        }

        return $this->csrs()->pluck('id')->contains($this->selectedCsrId)
            ? User::find($this->selectedCsrId)
            : null;
    }

    /**
     * @return LengthAwarePaginator<int, Order>
     */
    #[Computed]
    public function detailRecords(): LengthAwarePaginator
    {
        if ($this->selectedCsrId === null) {
            return new LengthAwarePaginator([], 0, 10, $this->getPage(), ['path' => LengthAwarePaginator::resolveCurrentPath()]);
        }

        [$from, $to] = DashboardDateScope::fromSession();

        $query = Order::query()
            ->where('is_migrated_order', false)
            ->whereBetween('created_at', [$from, $to])
            ->where('status', OrderStatus::Delivered)
            ->where('assigned_to', $this->selectedCsrId)
            ->with(['customer'])
            ->latest('created_at');

        if ($this->search !== '') {
            $search = '%'.strtolower($this->search).'%';
            $query->where(function (Builder $q) use ($search) {
                $q->orWhere('id', 'like', $search)
                    ->orWhereHas('customer', fn (Builder $cq) => $cq->whereRaw('LOWER(customer_name) LIKE ?', [$search]));
            });
        }

        return $query->paginate(10);
    }

    /**
     * @return Collection<int, User>
     */
    private function csrs(): Collection
    {
        return User::where('role', 'community_sales_representative')->active()->orderBy('name')->get();
    }

    public function render()
    {
        return view('livewire.csr-order-breakdown-table');
    }
}
