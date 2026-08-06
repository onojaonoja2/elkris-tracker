<?php

namespace App\Livewire;

use App\Enums\StockTransferStatus;
use App\Enums\UserRole;
use App\Models\DamagedStockReturn;
use App\Models\SalesRecord;
use App\Models\StockCount;
use App\Models\StockTransfer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class PendingApprovalBreakdownTable extends Component
{
    use WithPagination;

    private const array ALLOWED_TYPES = ['stock_transfer', 'stock_count', 'sales_records', 'damaged_return'];

    private const array OPEN_RETAIL_ROLES = [UserRole::OpenMarket->value, UserRole::RetailMarket->value];

    public string $type = 'stock_transfer';

    public string $search = '';

    public function mount(?string $type = null): void
    {
        if (! $this->isSupervisor() && ! $this->isManager()) {
            abort(403);
        }

        if ($type !== null) {
            $this->type = in_array($type, self::ALLOWED_TYPES, true) ? $type : 'stock_transfer';
        }
    }

    private function isSupervisor(): bool
    {
        return auth()->user()?->hasRole('supervisor') ?? false;
    }

    private function isManager(): bool
    {
        return auth()->user()?->hasAnyRole(['manager', 'admin']) ?? false;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, StockTransfer|StockCount|SalesRecord|DamagedStockReturn>
     */
    #[Computed]
    public function records(): LengthAwarePaginator
    {
        $scope = $this->isSupervisor() ? 'supervisor' : 'manager';

        return match ($this->type) {
            'stock_count' => $this->stockCountRecords($scope),
            'sales_records' => $this->salesRecordsRecords($scope),
            'damaged_return' => $this->damagedReturnRecords($scope),
            default => $this->stockTransferRecords($scope),
        };
    }

    /**
     * @return LengthAwarePaginator<int, StockTransfer>
     */
    private function stockTransferRecords(string $scope): LengthAwarePaginator
    {
        return StockTransfer::where('status', StockTransferStatus::Requested)
            ->whereNull('supervisor_approved_by')
            ->where('requires_approval', true)
            ->when($scope === 'supervisor', function (Builder $query) {
                $query->whereHas('requester', fn (Builder $query) => $query->where('role', UserRole::CommunitySalesRepresentative->value));
            })
            ->when($this->search !== '', function (Builder $query) {
                $search = '%'.strtolower($this->search).'%';

                $query->where(function (Builder $query) use ($search) {
                    $query->orWhere('id', 'like', $search)
                        ->orWhereHas('requester', fn (Builder $query) => $query->whereRaw('LOWER(name) LIKE ?', [$search]))
                        ->orWhereHas('toAgent', fn (Builder $query) => $query->whereRaw('LOWER(name) LIKE ?', [$search]));
                });
            })
            ->with(['requester', 'fromWarehouse', 'toAgent', 'items.productType'])
            ->latest('created_at')
            ->paginate(10);
    }

    /**
     * @return LengthAwarePaginator<int, StockCount>
     */
    private function stockCountRecords(string $scope): LengthAwarePaginator
    {
        return StockCount::where('status', 'pending')
            ->whereNull('supervisor_status')
            ->when($scope === 'supervisor', function (Builder $query) {
                $query->whereHas('user', fn (Builder $query) => $query->where('role', UserRole::CommunitySalesRepresentative->value));
            }, function (Builder $query) {
                $query->whereHas('user', fn (Builder $query) => $query->whereIn('role', self::OPEN_RETAIL_ROLES));
            })
            ->when($this->search !== '', function (Builder $query) {
                $search = '%'.strtolower($this->search).'%';

                $query->whereHas('user', fn (Builder $query) => $query->whereRaw('LOWER(name) LIKE ?', [$search]));
            })
            ->with(['user', 'items.productType'])
            ->latest('created_at')
            ->paginate(10);
    }

    /**
     * @return LengthAwarePaginator<int, SalesRecord>
     */
    private function salesRecordsRecords(string $scope): LengthAwarePaginator
    {
        return SalesRecord::whereIn('status', ['pending', 'receipt_uploaded'])
            ->whereNull('supervisor_verified_at')
            ->when($scope === 'supervisor', function (Builder $query) {
                $query->whereHas('agent', fn (Builder $query) => $query->where('role', UserRole::CommunitySalesRepresentative->value));
            }, function (Builder $query) {
                $query->whereHas('agent', fn (Builder $query) => $query->whereIn('role', self::OPEN_RETAIL_ROLES));
            })
            ->when($this->search !== '', function (Builder $query) {
                $search = '%'.strtolower($this->search).'%';

                $query->whereHas('agent', fn (Builder $query) => $query->whereRaw('LOWER(name) LIKE ?', [$search]));
            })
            ->with('agent')
            ->latest('created_at')
            ->paginate(10);
    }

    /**
     * @return LengthAwarePaginator<int, DamagedStockReturn>
     */
    private function damagedReturnRecords(string $scope): LengthAwarePaginator
    {
        return DamagedStockReturn::where('status', 'pending')
            ->whereNull('supervisor_approved_by')
            ->when($scope === 'supervisor', function (Builder $query) {
                $query->whereHas('user', fn (Builder $query) => $query->where('role', UserRole::CommunitySalesRepresentative->value));
            }, function (Builder $query) {
                $query->whereHas('user', fn (Builder $query) => $query->whereIn('role', self::OPEN_RETAIL_ROLES));
            })
            ->when($this->search !== '', function (Builder $query) {
                $search = '%'.strtolower($this->search).'%';

                $query->where(function (Builder $query) use ($search) {
                    $query->whereHas('user', fn (Builder $query) => $query->whereRaw('LOWER(name) LIKE ?', [$search]))
                        ->orWhereHas('productType', fn (Builder $query) => $query->whereRaw('LOWER(name) LIKE ?', [$search]));
                });
            })
            ->with(['user', 'warehouse', 'productType'])
            ->latest('created_at')
            ->paginate(10);
    }

    public function render()
    {
        return view('livewire.pending-approval-breakdown-table');
    }
}
