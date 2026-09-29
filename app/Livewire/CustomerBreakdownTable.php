<?php

namespace App\Livewire;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\User;
use App\Support\DashboardDateScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerBreakdownTable extends Component
{
    use WithPagination;

    private const array AGENT_GROUPS = [
        'csr' => 'community_sales_representative',
        'open_market' => 'open_market',
        'retail_market' => 'retail_market',
    ];

    private const array EXPORT_HEADERS = [
        'Customer', 'Phone', 'Address', 'City', 'State', 'Added By', 'Date Added', 'Orders', 'Total Ordered Value',
    ];

    public string $level = 'groups';

    public ?string $group = null;

    public ?int $userId = null;

    public string $search = '';

    /**
     * @return array{0: string, 1: string}
     */
    #[Computed]
    public function scope(): array
    {
        [$from, $to] = DashboardDateScope::fromSession();

        return [$from->toDateTimeString(), $to->toDateTimeString()];
    }

    /**
     * @return Collection<int, object{key: string, label: string, type: string, count: int, user_id: ?int}>
     */
    #[Computed]
    public function groups(): Collection
    {
        [$from, $to] = $this->scope;

        $repCounts = Customer::whereBetween('created_at', [$from, $to])
            ->whereNotNull('rep_id')
            ->selectRaw('rep_id as user_id, COUNT(*) as c')
            ->groupBy('rep_id')
            ->pluck('c', 'user_id');

        $leadCounts = Customer::whereBetween('created_at', [$from, $to])
            ->whereNotNull('lead_id')
            ->selectRaw('lead_id as user_id, COUNT(*) as c')
            ->groupBy('lead_id')
            ->pluck('c', 'user_id');

        $agentCounts = Customer::whereBetween('created_at', [$from, $to])
            ->whereNotNull('agent_id')
            ->selectRaw('agent_id as user_id, COUNT(*) as c')
            ->groupBy('agent_id')
            ->pluck('c', 'user_id');

        $unassigned = Customer::whereBetween('created_at', [$from, $to])
            ->whereNull('agent_id')
            ->whereNull('rep_id')
            ->whereNull('lead_id')
            ->count();

        $rows = collect();

        User::where('role', 'rep')->active()->orderBy('name')->get()->each(
            fn (User $user) => $rows->push((object) [
                'key' => 'rep-'.$user->id,
                'label' => $user->name,
                'type' => 'rep',
                'count' => (int) ($repCounts->get($user->id) ?? 0),
                'user_id' => $user->id,
            ])
        );

        User::where('role', 'lead')->active()->orderBy('name')->get()->each(
            fn (User $user) => $rows->push((object) [
                'key' => 'lead-'.$user->id,
                'label' => $user->name,
                'type' => 'lead',
                'count' => (int) ($leadCounts->get($user->id) ?? 0)
                    + $this->leadRepCustomerCount($user, $from, $to),
                'user_id' => $user->id,
            ])
        );

        foreach (self::AGENT_GROUPS as $groupKey => $role) {
            $roleIds = User::where('role', $role)->pluck('id');

            $rows->push((object) [
                'key' => 'group-'.$groupKey,
                'label' => self::groupLabel($groupKey),
                'type' => 'group',
                'count' => $roleIds->isEmpty()
                    ? 0
                    : (int) $agentCounts->filter(fn ($count, $userId) => $roleIds->contains($userId))->sum(),
                'user_id' => null,
            ]);
        }

        $rows->push((object) [
            'key' => 'group-unassigned',
            'label' => 'Unassigned',
            'type' => 'unassigned',
            'count' => $unassigned,
            'user_id' => null,
        ]);

        return $rows->filter(fn (object $row): bool => $row->count > 0)->values();
    }

    private function leadRepCustomerCount(User $lead, string $from, string $to): int
    {
        $repIds = $lead->reps()->pluck('id');

        if ($repIds->isEmpty()) {
            return 0;
        }

        return Customer::whereBetween('created_at', [$from, $to])
            ->whereIn('rep_id', $repIds)
            ->count();
    }

    /**
     * @return Collection<int, object{id: int, name: string, count: int}>
     */
    #[Computed]
    public function agents(): Collection
    {
        $role = self::AGENT_GROUPS[$this->group] ?? null;

        if (! $role) {
            return collect();
        }

        [$from, $to] = $this->scope;

        $counts = Customer::whereBetween('created_at', [$from, $to])
            ->whereNotNull('agent_id')
            ->selectRaw('agent_id as user_id, COUNT(*) as c')
            ->groupBy('agent_id')
            ->pluck('c', 'user_id');

        return User::where('role', $role)
            ->active()
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user): bool => blank($this->search)
                || str_contains(strtolower($user->name), strtolower($this->search)))
            ->map(fn (User $user) => (object) [
                'id' => $user->id,
                'name' => $user->name,
                'count' => (int) ($counts->get($user->id) ?? 0),
            ]);
    }

    #[Computed]
    public function customers(): LengthAwarePaginator
    {
        [$from, $to] = $this->scope;

        return $this->customerQuery()
            ->whereBetween('created_at', [$from, $to])
            ->when(filled($this->search), function ($query) {
                $query->where(function ($q) {
                    $q->where('customer_name', 'like', '%'.$this->search.'%')
                        ->orWhere('phone_number', 'like', '%'.$this->search.'%');
                });
            })
            ->with(['agent', 'rep', 'lead'])
            ->withCount([
                'orders as orders_count' => fn ($q) => $q
                    ->where('status', '!=', OrderStatus::Cancelled)
                    ->where('is_migrated_order', false),
            ])
            ->withSum([
                'orders as orders_total' => fn ($q) => $q
                    ->where('status', '!=', OrderStatus::Cancelled)
                    ->where('is_migrated_order', false),
            ], 'total_price')
            ->orderBy('created_at', 'desc')
            ->paginate(10);
    }

    private function customerQuery()
    {
        if (self::AGENT_GROUPS[$this->group] ?? null) {
            return Customer::where('agent_id', $this->userId);
        }

        if ($this->userId === null) {
            return Customer::whereRaw('1 = 0');
        }

        $user = User::find($this->userId);

        if (! $user) {
            return Customer::whereRaw('1 = 0');
        }

        if ($user->getPrimaryRole() === 'rep') {
            return Customer::where('rep_id', $user->id);
        }

        if ($user->getPrimaryRole() === 'lead') {
            $repIds = $user->reps()->pluck('id');

            return Customer::where(function ($q) use ($user, $repIds) {
                $q->where('lead_id', $user->id);

                if ($repIds->isNotEmpty()) {
                    $q->orWhereIn('rep_id', $repIds);
                }
            });
        }

        return Customer::where('agent_id', $user->id);
    }

    #[Computed]
    public function selectedUser(): ?User
    {
        return $this->userId ? User::find($this->userId) : null;
    }

    #[Computed]
    public function currentLabel(): string
    {
        if ($this->level === 'customers') {
            return $this->selectedUser?->name ?? 'Customers';
        }

        return self::groupLabel($this->group);
    }

    public function selectGroup(string $group): void
    {
        $this->level = 'agents';
        $this->group = $group;
        $this->userId = null;
        $this->resetPage();
    }

    public function selectAgent(int $userId): void
    {
        $this->level = 'customers';
        $this->userId = $userId;
        $this->resetPage();
    }

    public function selectUser(int $userId): void
    {
        $this->level = 'customers';
        $this->group = null;
        $this->userId = $userId;
        $this->resetPage();
    }

    public function back(): void
    {
        if ($this->level === 'customers' && $this->group !== null) {
            $this->level = 'agents';
            $this->userId = null;
        } else {
            $this->level = 'groups';
            $this->group = null;
            $this->userId = null;
        }

        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function exportCsv(): StreamedResponse
    {
        $rows = $this->exportRows();

        $filename = 'customer_breakdown_'.date('Y_m_d_H_i_s').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, self::EXPORT_HEADERS);

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function exportExcel(): StreamedResponse
    {
        $rows = $this->exportRows();

        $filename = 'customer_breakdown_'.date('Y_m_d_H_i_s').'.xlsx';

        return response()->streamDownload(function () use ($rows) {
            $path = tempnam(sys_get_temp_dir(), 'customers_').'.xlsx';

            $writer = new Writer;
            $writer->openToFile($path);
            $writer->addRow(Row::fromValues(self::EXPORT_HEADERS));

            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues($row));
            }

            $writer->close();

            readfile($path);
            unlink($path);
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function exportRows(): array
    {
        [$from, $to] = $this->scope;

        return $this->customerQuery()
            ->whereBetween('created_at', [$from, $to])
            ->when(filled($this->search), function ($query) {
                $query->where(function ($q) {
                    $q->where('customer_name', 'like', '%'.$this->search.'%')
                        ->orWhere('phone_number', 'like', '%'.$this->search.'%');
                });
            })
            ->with(['agent', 'rep', 'lead'])
            ->withCount([
                'orders as orders_count' => fn ($q) => $q
                    ->where('status', '!=', OrderStatus::Cancelled)
                    ->where('is_migrated_order', false),
            ])
            ->withSum([
                'orders as orders_total' => fn ($q) => $q
                    ->where('status', '!=', OrderStatus::Cancelled)
                    ->where('is_migrated_order', false),
            ], 'total_price')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (Customer $customer) => [
                $customer->customer_name,
                $customer->phone_number ?? '',
                $customer->address ?? '',
                $customer->city ?? '',
                $customer->state ?? '',
                $customer->agent?->name ?? '-',
                $customer->created_at?->format('d M Y H:i') ?? '',
                (string) ($customer->orders_count ?? 0),
                number_format((float) ($customer->orders_total ?? 0), 2),
            ])
            ->all();
    }

    public static function groupLabel(?string $group): string
    {
        return match ($group) {
            'csr' => 'Community Sales Representatives',
            'open_market' => 'Open Market Agents',
            'retail_market' => 'Retail Market Agents',
            default => 'Agents',
        };
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'rep' => 'Elkris Portfolio Agent',
            'lead' => 'Team Lead',
            'group' => 'Group',
            default => 'Unassigned',
        };
    }

    public function render()
    {
        return view('livewire.customer-breakdown-table');
    }
}
