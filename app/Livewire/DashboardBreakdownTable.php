<?php

namespace App\Livewire;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\SalesRecord;
use App\Models\User;
use App\Support\DashboardDateScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class DashboardBreakdownTable extends Component
{
    use WithPagination;

    public string $type = 'order';

    public ?string $category = null;

    public string $search = '';

    public ?string $statusFilter = null;

    public function mount(string $type, ?string $category = null): void
    {
        $this->type = in_array($type, ['credit', 'order'], true) ? $type : 'order';
        $this->category = $category;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function statusOptions(): array
    {
        if ($this->type === 'credit') {
            return [
                'pending_payment' => 'Pending Payment',
                'partially_collected' => 'Partially Collected',
                'overdue' => 'Overdue',
            ];
        }

        return [
            'pending' => 'Pending',
            'delivered' => 'Delivered',
        ];
    }

    #[Computed]
    public function records(): LengthAwarePaginator
    {
        [$from, $to] = DashboardDateScope::fromSession();

        $query = $this->type === 'credit'
            ? $this->creditQuery($from, $to)
            : $this->orderQuery($from, $to);

        return $query->paginate(10);
    }

    private function creditQuery(string $from, string $to): Builder
    {
        $query = SalesRecord::outstanding()
            ->whereBetween('created_at', [$from, $to])
            ->with('agent');

        $user = auth()->user();
        $role = $user?->getPrimaryRole();

        if (in_array($role, ['community_sales_representative', 'open_market', 'retail_market'], true) || $this->category === 'my') {
            $query->where('agent_id', $user->id);
        } elseif ($role === 'supervisor' && in_array($this->category, ['csr', 'community_sales_representative'], true)) {
            $csrIds = User::where('role', 'community_sales_representative')->active()->pluck('id');
            $query->whereIn('agent_id', $csrIds);
        } elseif ($this->category === 'csr') {
            $csrIds = User::where('role', 'community_sales_representative')->active()->pluck('id');
            $query->whereIn('agent_id', $csrIds);
        }

        if (in_array($this->category, ['open_market', 'retail_market', 'community_sales_representative'], true)) {
            $query->where('agent_type', $this->category);
        }

        if ($this->category === 'overdue' || $this->statusFilter === 'overdue') {
            $query->where('expected_collection_date', '<', now()->toDateString());
        } elseif ($this->statusFilter) {
            $query->where('credit_status', $this->statusFilter);
        }

        $this->applySearch($query, ['customer_name'], 'agent');

        return $query->latest('created_at');
    }

    private function orderQuery(string $from, string $to): Builder
    {
        $query = Order::query()
            ->where('is_migrated_order', false)
            ->whereBetween('created_at', [$from, $to])
            ->with(['customer', 'user', 'assignedTo']);

        $user = auth()->user();
        $role = $user?->getPrimaryRole();
        $isCsr = $role === 'community_sales_representative';
        $isSales = $role === 'sales';

        if (in_array($role, ['rep', 'lead', 'sales', 'community_sales_representative', 'open_market', 'retail_market'], true) || $this->category === 'my') {
            if ($isCsr) {
                $query->where('assigned_to', $user->id);
            } elseif ($isSales) {
                $query->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('assigned_to', $user->id));
            } else {
                $query->where('user_id', $user->id);
            }
        } elseif ($role === 'supervisor' && $this->category !== 'total' && ! in_array($this->category, ['open_market', 'retail_market', 'community_sales_representative'], true)) {
            $csrIds = User::where('role', 'community_sales_representative')->active()->pluck('id');
            $query->whereIn('assigned_to', $csrIds);
        } elseif ($this->category === 'csr') {
            $csrIds = User::where('role', 'community_sales_representative')->active()->pluck('id');
            $query->whereIn('assigned_to', $csrIds);
        }

        if ($this->category === 'pending') {
            $query->pendingDelivery();
        } elseif ($this->category === 'delivered') {
            $query->where('status', OrderStatus::Delivered);
        }

        if (in_array($this->category, ['open_market', 'retail_market'], true)) {
            $query->whereHas('user', fn ($q) => $q->where('role', $this->category));
        } elseif ($this->category === 'community_sales_representative') {
            $csrIds = User::where('role', 'community_sales_representative')->active()->pluck('id');
            $query->whereIn('assigned_to', $csrIds);
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        $this->applySearch($query, ['id'], 'customer', 'user');

        return $query->latest('created_at');
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function applySearch(Builder $query, array $columns, string ...$relations): void
    {
        if (blank($this->search)) {
            return;
        }

        $search = '%'.strtolower($this->search).'%';

        $query->where(function (Builder $q) use ($columns, $relations, $search) {
            foreach ($columns as $column) {
                if ($column === 'id') {
                    $q->orWhere($column, 'like', $search);
                } else {
                    $q->orWhereRaw("LOWER({$column}) LIKE ?", [$search]);
                }
            }

            foreach ($relations as $relation) {
                $q->orWhereHas($relation, function (Builder $rq) use ($search) {
                    $rq->whereRaw('LOWER(name) LIKE ?', [$search]);
                });
            }
        });
    }

    public function render()
    {
        return view('livewire.dashboard-breakdown-table');
    }
}
