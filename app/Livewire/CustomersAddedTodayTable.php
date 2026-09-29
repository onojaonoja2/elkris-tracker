<?php

namespace App\Livewire;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomersAddedTodayTable extends Component
{
    use WithPagination;

    private const array EXPORT_HEADERS = [
        'Customer', 'Phone', 'City', 'State', 'Date Added', 'Added By', 'Added By Role', 'Orders', 'Total Ordered Value',
    ];

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public string $search = '';

    public function mount(): void
    {
        $this->dateFrom ??= now()->toDateString();
        $this->dateTo ??= now()->toDateString();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    #[Computed]
    public function range(): array
    {
        $from = $this->dateFrom ? Carbon::parse($this->dateFrom)->startOfDay() : now()->startOfDay();
        $to = $this->dateTo ? Carbon::parse($this->dateTo)->endOfDay() : now()->endOfDay();

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }

    #[Computed]
    public function customers(): LengthAwarePaginator
    {
        [$from, $to] = $this->range;

        return $this->exportQuery($from, $to)
            ->paginate(10);
    }

    private function exportQuery(Carbon $from, Carbon $to)
    {
        return Customer::whereBetween('created_at', [$from, $to])
            ->when(filled($this->search), function ($query) {
                $query->where(function ($q) {
                    $q->where('customer_name', 'like', '%'.$this->search.'%')
                        ->orWhere('phone_number', 'like', '%'.$this->search.'%');
                });
            })
            ->with('agent')
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
            ->orderBy('created_at', 'desc');
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function exportCsv(): StreamedResponse
    {
        $rows = $this->exportRows();

        $filename = 'customers_added_'.date('Y_m_d_H_i_s').'.csv';

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

        $filename = 'customers_added_'.date('Y_m_d_H_i_s').'.xlsx';

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
        [$from, $to] = $this->range;

        return $this->exportQuery($from, $to)
            ->get()
            ->map(fn (Customer $customer) => [
                $customer->customer_name,
                $customer->phone_number ?? '',
                $customer->city ?? '',
                $customer->state ?? '',
                $customer->created_at?->format('d M Y H:i') ?? '',
                $customer->agent?->name ?? '-',
                self::roleLabel($customer->agent),
                (string) ($customer->orders_count ?? 0),
                number_format((float) ($customer->orders_total ?? 0), 2),
            ])
            ->all();
    }

    public static function roleLabel(?User $user): string
    {
        if (! $user) {
            return '-';
        }

        return match ($user->getPrimaryRole()) {
            'community_sales_representative' => 'CSR',
            'open_market' => 'Open Market',
            'retail_market' => 'Retail Market',
            'field_agent' => 'Field Agent',
            'rep' => 'Elkris Portfolio Agent',
            'lead' => 'Team Lead',
            'sales' => 'Sales',
            default => ucfirst(str_replace('_', ' ', $user->getPrimaryRole())),
        };
    }

    public function render()
    {
        return view('livewire.customers-added-today-table');
    }
}
