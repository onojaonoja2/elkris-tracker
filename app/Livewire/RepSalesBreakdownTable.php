<?php

namespace App\Livewire;

use App\Models\Order;
use App\Models\User;
use App\Support\DashboardDateScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class RepSalesBreakdownTable extends Component
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

    public int $userId;

    public ?string $selectedDate = null;

    public string $search = '';

    public function mount(int $userId): void
    {
        $this->userId = $userId;

        if (! auth()->user()?->hasAnyRole(self::ALLOWED_ROLES)) {
            abort(403);
        }
    }

    public function selectDate(string $date): void
    {
        $this->selectedDate = $date;
        $this->resetPage();
    }

    public function back(): void
    {
        $this->selectedDate = null;
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function person(): ?User
    {
        return User::with('lead')->find($this->userId);
    }

    /**
     * @return Collection<int, int>
     */
    #[Computed]
    public function customerIds(): Collection
    {
        $person = $this->person;
        if (! $person) {
            return collect();
        }

        $relation = $person->role === 'lead' ? 'leadCustomers' : 'repCustomers';

        return $person->{$relation}()->pluck('customers.id')->map(fn (int $id): int => $id)->values();
    }

    /**
     * @return LengthAwarePaginator<int, array{date: string, label: string, order_count: int, total: float}>
     */
    #[Computed]
    public function dailyRecords(): LengthAwarePaginator
    {
        [$from, $to] = DashboardDateScope::fromSession();

        $orders = Order::whereIn('customer_id', $this->customerIds)
            ->whereIn('status', self::COUNTED_ORDER_STATUSES)
            ->whereBetween('created_at', [$from, $to])
            ->get(['created_at', 'total_price']);

        $days = $orders
            ->groupBy(fn (Order $order): string => $order->created_at->toDateString())
            ->map(fn (Collection $dayOrders, string $date): array => [
                'date' => $date,
                'label' => Carbon::parse($date)->format('M d, Y'),
                'order_count' => $dayOrders->count(),
                'total' => (float) $dayOrders->sum('total_price'),
            ])
            ->sortByDesc('date')
            ->values();

        $page = $this->getPage();

        return new LengthAwarePaginator(
            $days->forPage($page, 10)->values(),
            $days->count(),
            10,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()],
        );
    }

    /**
     * @return LengthAwarePaginator<int, Order>
     */
    #[Computed]
    public function dayOrders(): LengthAwarePaginator
    {
        if ($this->selectedDate === null) {
            return new LengthAwarePaginator([], 0, 10, $this->getPage(), ['path' => LengthAwarePaginator::resolveCurrentPath()]);
        }

        [$from, $to] = DashboardDateScope::fromSession();

        $query = Order::whereIn('customer_id', $this->customerIds)
            ->whereIn('status', self::COUNTED_ORDER_STATUSES)
            ->whereBetween('created_at', [$from, $to])
            ->whereDate('created_at', $this->selectedDate)
            ->with(['customer', 'products'])
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

    public function render()
    {
        return view('livewire.rep-sales-breakdown-table');
    }
}
