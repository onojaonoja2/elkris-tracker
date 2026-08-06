<div class="space-y-4">
    @if($this->selectedCsrId !== null)
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <button
                type="button"
                wire:click="backToSummary"
                class="inline-flex items-center gap-1 text-sm text-primary-600 hover:text-primary-500"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
                Back to summary
            </button>
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                {{ $this->selectedCsr?->name ?? 'CSR' }} — Completed Orders
            </span>
        </div>
    @endif

    <div class="flex flex-col sm:flex-row gap-3">
        <input
            type="text"
            wire:model.live.debounce.300ms="search"
            placeholder="{{ $this->selectedCsrId !== null ? 'Search by order # or customer...' : 'Search by CSR name...' }}"
            class="w-full sm:w-64 px-3 py-2 text-sm border rounded-lg dark:bg-gray-800 dark:border-gray-700"
        />
    </div>

    <div class="overflow-auto max-h-[60vh]">
        @if($this->selectedCsrId !== null)
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-100 dark:bg-gray-800">
                    <tr>
                        <th class="px-3 py-2">Order #</th>
                        <th class="px-3 py-2">Customer</th>
                        <th class="px-3 py-2">Value</th>
                        <th class="px-3 py-2">Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->detailRecords as $record)
                        <tr class="border-b">
                            <td class="px-3 py-2">#{{ $record->id }}</td>
                            <td class="px-3 py-2">{{ $record->customer?->customer_name ?? '-' }}</td>
                            <td class="px-3 py-2">₦{{ number_format($record->total_price, 2) }}</td>
                            <td class="px-3 py-2">{{ $record->created_at->format('M d, Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-3 py-4 text-center text-gray-500">No completed orders found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        @else
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-100 dark:bg-gray-800">
                    <tr>
                        <th class="px-3 py-2">CSR</th>
                        <th class="px-3 py-2">Completed Orders</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->summaryRows as $row)
                        <tr class="border-b">
                            <td class="px-3 py-2">{{ $row['name'] }}</td>
                            <td class="px-3 py-2">{{ $row['completed'] }}</td>
                            <td class="px-3 py-2 text-right">
                                <button
                                    type="button"
                                    wire:click="selectCsr({{ $row['id'] }})"
                                    class="inline-flex items-center gap-1 text-sm text-primary-600 hover:text-primary-500"
                                >
                                    View Orders
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-3 py-4 text-center text-gray-500">No CSRs found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        @endif
    </div>

    <div class="flex justify-center">
        @if($this->selectedCsrId !== null)
            {{ $this->detailRecords->links(data: ['scrollTo' => false]) }}
        @else
            {{ $this->summaryRows->links(data: ['scrollTo' => false]) }}
        @endif
    </div>
</div>
