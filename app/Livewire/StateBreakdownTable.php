<?php

namespace App\Livewire;

use App\Models\Lga;
use App\Models\SalesRecord;
use App\Models\State;
use App\Models\User;
use App\Support\DashboardDateScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StateBreakdownTable extends Component
{
    use WithPagination;

    public string $entity = 'people';

    public int $stateId = 0;

    public string $search = '';

    public function mount(string $entity, int $stateId): void
    {
        $this->entity = in_array($entity, ['people', 'sales', 'credit'], true) ? $entity : 'people';
        $this->stateId = $stateId;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function stateName(): string
    {
        return State::whereKey($this->stateId)->value('name') ?? 'Unknown';
    }

    #[Computed]
    public function records(): LengthAwarePaginator
    {
        return $this->breakdownQuery()->paginate(5);
    }

    private function breakdownQuery(): Builder
    {
        return match ($this->entity) {
            'sales' => $this->salesQuery(),
            'credit' => $this->creditQuery(),
            default => $this->peopleQuery(),
        };
    }

    /**
     * @return array<int, int>
     */
    private function stateLgaIds(): array
    {
        return Lga::where('state_id', $this->stateId)->pluck('id')->all();
    }

    /**
     * @return array<int, int>
     */
    private function stateAgentIds(): array
    {
        return User::whereIn('lga_id', $this->stateLgaIds())->pluck('id')->all();
    }

    private function peopleQuery(): Builder
    {
        return User::query()
            ->whereIn('role', ['field_agent', 'community_sales_representative', 'open_market', 'retail_market', 'lead', 'rep'])
            ->whereIn('lga_id', $this->stateLgaIds())
            ->when(filled($this->search), fn (Builder $query) => $query->where(function (Builder $query) {
                $search = '%'.$this->search.'%';
                $query->where('name', 'like', $search)
                    ->orWhere('email', 'like', $search)
                    ->orWhere('phone', 'like', $search);
            }))
            ->with('lga')
            ->orderBy('name');
    }

    private function salesQuery(): Builder
    {
        return SalesRecord::query()
            ->whereIn('agent_id', $this->stateAgentIds())
            ->when(filled($this->search), fn (Builder $query) => $query->where(function (Builder $query) {
                $search = '%'.$this->search.'%';
                $query->where('id', 'like', $search)
                    ->orWhere('customer_name', 'like', $search)
                    ->orWhereHas('agent', fn (Builder $query) => $query->where('name', 'like', $search));
            }))
            ->with('agent')
            ->latest('created_at');
    }

    private function creditQuery(): Builder
    {
        [$from, $to] = DashboardDateScope::fromSession();

        return $this->salesQuery()
            ->where('is_credit', true)
            ->where('status', 'approved')
            ->whereBetween('created_at', [$from, $to]);
    }

    public function exportCsv(): StreamedResponse
    {
        $query = $this->breakdownQuery();
        $entity = $this->entity;
        $stateName = $this->stateName;

        $filename = "state_{$entity}_".str($stateName)->slug('_').'_'.date('Y_m_d_H_i_s').'.csv';

        return response()->streamDownload(function () use ($query, $entity) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            if ($entity === 'people') {
                fputcsv($handle, ['Name', 'Email', 'Phone', 'Role', 'Status', 'LGA']);

                $query->with('lga')->each(function (User $user) use ($handle) {
                    fputcsv($handle, [
                        $user->name,
                        $user->email,
                        $user->phone ?? '-',
                        str_replace('_', ' ', ucfirst($user->role)),
                        $user->is_active ? 'Active' : 'Inactive',
                        $user->lga?->name ?? '-',
                    ]);
                });
            } else {
                $isCredit = $entity === 'credit';
                fputcsv($handle, $isCredit
                    ? ['Record #', 'Agent', 'Customer', 'Value', 'Credit Status', 'Date']
                    : ['Record #', 'Agent', 'Customer', 'Value', 'Status', 'Date']);

                $query->with('agent')->each(function (SalesRecord $record) use ($handle, $isCredit) {
                    fputcsv($handle, [
                        $record->id,
                        $record->agent?->name ?? 'N/A',
                        $record->customer_name ?? '-',
                        (float) $record->total_value,
                        $isCredit ? str_replace('_', ' ', ucfirst($record->credit_status ?? '')) : $record->status,
                        $record->created_at?->format('d/m/Y H:i'),
                    ]);
                });
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function render()
    {
        return view('livewire.state-breakdown-table');
    }
}
