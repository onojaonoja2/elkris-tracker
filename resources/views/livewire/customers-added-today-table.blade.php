<div class="space-y-4">
    <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-center">
        <div class="flex items-center gap-2">
            <label class="text-sm font-medium">From</label>
            <input
                type="date"
                wire:model.live="dateFrom"
                class="px-3 py-2 text-sm border rounded-lg dark:bg-gray-800 dark:border-gray-700"
            />
        </div>
        <div class="flex items-center gap-2">
            <label class="text-sm font-medium">To</label>
            <input
                type="date"
                wire:model.live="dateTo"
                class="px-3 py-2 text-sm border rounded-lg dark:bg-gray-800 dark:border-gray-700"
            />
        </div>
        <input
            type="text"
            wire:model.live.debounce.300ms="search"
            placeholder="Search customer..."
            class="w-full sm:w-56 px-3 py-2 text-sm border rounded-lg dark:bg-gray-800 dark:border-gray-700"
        />
        <div class="flex gap-2">
            <button type="button" wire:click="exportCsv" class="px-3 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">
                Export CSV
            </button>
            <button type="button" wire:click="exportExcel" class="px-3 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700">
                Export Excel
            </button>
        </div>
    </div>

    <div class="overflow-auto max-h-[60vh]">
        <table class="w-full text-sm text-left">
            <thead class="bg-gray-100 dark:bg-gray-800">
                <tr>
                    <th class="px-3 py-2">Customer</th>
                    <th class="px-3 py-2">Phone</th>
                    <th class="px-3 py-2">Date Added</th>
                    <th class="px-3 py-2">Added By</th>
                    <th class="px-3 py-2">Orders</th>
                    <th class="px-3 py-2">Order Value</th>
                </tr>
            </thead>
            <tbody>
                @forelse($this->customers as $customer)
                    <tr class="border-b">
                        <td class="px-3 py-2 font-medium">{{ $customer->customer_name }}</td>
                        <td class="px-3 py-2">{{ $customer->phone_number ?? '-' }}</td>
                        <td class="px-3 py-2">{{ $customer->created_at?->format('M d, Y H:i') }}</td>
                        <td class="px-3 py-2">
                            {{ $customer->agent?->name ?? '-' }}
                            @if($customer->agent)
                                <span class="px-2 py-0.5 ml-1 text-xs rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200">
                                    {{ \App\Livewire\CustomersAddedTodayTable::roleLabel($customer->agent) }}
                                </span>
                            @endif
                        </td>
                        <td class="px-3 py-2">{{ $customer->orders_count }}</td>
                        <td class="px-3 py-2 font-semibold">₦{{ number_format((float) $customer->orders_total, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-3 py-4 text-center text-gray-500">No customers added in the selected period.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="flex justify-center">
        {{ $this->customers->links(data: ['scrollTo' => false]) }}
    </div>
</div>
