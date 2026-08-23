<?php

namespace App\Livewire;

use App\Models\Order;
use App\Models\User;
use App\Support\DashboardDateScope;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class RepLeadEntityBreakdownTable extends Component
{
    use WithPagination;

    private const array ALLOWED_ROLES = [
        'accountant',
        'general_accountant',
    ];

    private const array COUNTED_ORDER_STATUSES = [
        'delivered',
        'confirmed',
        'completed',
    ];

    public string $search = '';

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

    /**
     * @return LengthAwarePaginator<int, array{id: int, name: string, role: string, lead_name: ?string, order_count: int, total_sales: float}>
     */
    #[Computed]
    public function rows(): LengthAwarePaginator
    {
        [$from, $to] = DashboardDateScope::fromSession();

        return User::whereIn('role', ['rep', 'lead'])
            ->with('lead')
            ->orderBy('role')
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user): bool => $this->search === ''
                || str_contains(strtolower($user->name), strtolower($this->search)))
            ->map(fn (User $user): array => $this->aggregateFor($user, $from, $to))
            ->sortByDesc('total_sales')
            ->values()
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

    /**
     * @return array{id: int, name: string, role: string, lead_name: ?string, order_count: int, total_sales: float}
     */
    private function aggregateFor(User $user, Carbon $from, Carbon $to): array
    {
        $relation = $user->role === 'lead' ? 'leadCustomers' : 'repCustomers';

        $customerIds = $user->{$relation}()->pluck('customers.id');

        $orders = Order::whereIn('customer_id', $customerIds)
            ->whereIn('status', self::COUNTED_ORDER_STATUSES)
            ->whereBetween('created_at', [$from, $to])
            ->get(['total_price']);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'role' => $user->role,
            'lead_name' => $user->lead?->name,
            'order_count' => $orders->count(),
            'total_sales' => (float) $orders->sum('total_price'),
        ];
    }

    public function render()
    {
        return view('livewire.rep-lead-entity-breakdown-table');
    }
}
