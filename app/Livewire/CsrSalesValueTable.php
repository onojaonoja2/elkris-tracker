<?php

namespace App\Livewire;

use App\Models\SalesRecord;
use App\Models\User;
use App\Support\DashboardDateScope;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class CsrSalesValueTable extends Component
{
    use WithPagination;

    private const array CSV_HEADERS = ['CSR Name', 'Sales Count', 'Pending Count', 'Sales Value (NGN)', 'Revenue (NGN)'];

    public ?int $csrId = null;

    public string $search = '';

    public function mount(?int $csrId = null): void
    {
        $this->csrId = $csrId;
    }

    public function selectCsr(int $csrId): void
    {
        $this->csrId = $csrId;
        $this->resetPage();
    }

    public function back(): void
    {
        $this->csrId = null;
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
        $scope = DashboardDateScope::fromSession();

        return [$scope[0]->toDateTimeString(), $scope[1]->toDateTimeString()];
    }

    /**
     * @return Collection<int, object{id: int, name: string, lga: ?string, state: ?string, sales_count: int, sales_value: float, pending_count: int, revenue: float}>
     */
    #[Computed]
    public function csrs(): Collection
    {
        [$from, $to] = $this->scope();

        $csrIds = User::where('role', 'community_sales_representative')->active()->pluck('id');

        $salesAgg = SalesRecord::whereIn('agent_id', $csrIds)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('agent_id, COUNT(*) as sales_count, COALESCE(SUM(total_value), 0) as sales_value')
            ->groupBy('agent_id')
            ->get()
            ->keyBy('agent_id');

        $pendingAgg = SalesRecord::whereIn('agent_id', $csrIds)
            ->whereIn('status', ['pending', 'receipt_uploaded'])
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('agent_id, COUNT(*) as pending_count')
            ->groupBy('agent_id')
            ->get()
            ->keyBy('agent_id');

        $revenue = SalesRecord::revenueByAgent($csrIds->all(), Carbon::parse($from), Carbon::parse($to));

        return User::whereIn('id', $csrIds)
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
    public function selectedCsr(): ?User
    {
        return $this->csrId ? User::find($this->csrId) : null;
    }

    #[Computed]
    public function csrRecords(): LengthAwarePaginator
    {
        [$from, $to] = $this->scope();

        return SalesRecord::where('agent_id', $this->csrId)
            ->whereBetween('created_at', [$from, $to])
            ->with('agent')
            ->latest('created_at')
            ->paginate(10);
    }

    public function exportCsv()
    {
        $rows = $this->csrs;

        $filename = 'csr_sales_value_'.date('Y_m_d_H_i_s').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, self::CSV_HEADERS);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->name,
                    $row->sales_count,
                    $row->pending_count,
                    number_format($row->sales_value, 2),
                    number_format($row->revenue, 2),
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function render()
    {
        return view('livewire.csr-sales-value-table');
    }
}
