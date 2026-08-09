<div class="space-y-4">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        @if($this->selectedDate !== null)
            <button
                type="button"
                wire:click="back"
                class="inline-flex items-center gap-1 text-sm text-primary-600 hover:text-primary-500"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
                Back to daily records
            </button>
        @endif
        <div>
            <h4 class="text-lg font-semibold">{{ $this->person?->name ?? 'Sales Person' }}</h4>
            <p class="text-sm text-gray-500">
                {{ $this->person ? ucwords(str_replace('_', ' ', $this->person->role)) : '' }}
                @if($this->person?->lead)
                    · Team Lead: {{ $this->person->lead->name }}
                @endif
            </p>
        </div>
    </div>

    @if($this->selectedDate !== null)
        <div class="flex flex-col sm:flex-row gap-3">
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Search by order # or customer..."
                class="w-full sm:w-64 px-3 py-2 text-sm border rounded-lg dark:bg-gray-800 dark:border-gray-700"
            />
        </div>
    @endif

    <div class="overflow-auto max-h-[60vh]">
        @if($this->selectedDate === null)
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-100 dark:bg-gray-800">
                    <tr>
                        <th class="px-3 py-2">Date</th>
                        <th class="px-3 py-2">Orders</th>
                        <th class="px-3 py-2">Total Sales</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->dailyRecords as $day)
                        <tr class="border-b">
                            <td class="px-3 py-2 font-medium">{{ $day['label'] }}</td>
                            <td class="px-3 py-2">{{ $day['order_count'] }}</td>
                            <td class="px-3 py-2">₦{{ number_format($day['total'], 2) }}</td>
                            <td class="px-3 py-2 text-right">
                                <button
                                    type="button"
                                    wire:click="selectDate('{{ $day['date'] }}')"
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
                            <td colspan="4" class="px-3 py-4 text-center text-gray-500">No sales records in the selected period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        @else
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-100 dark:bg-gray-800">
                    <tr>
                        <th class="px-3 py-2">Order #</th>
                        <th class="px-3 py-2">Customer</th>
                        <th class="px-3 py-2">Products</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">Value</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->dayOrders as $record)
                        <tr class="border-b">
                            <td class="px-3 py-2">#{{ $record->id }}</td>
                            <td class="px-3 py-2">{{ $record->customer?->customer_name ?? '-' }}</td>
                            <td class="px-3 py-2">{{ $record->products->map(fn ($p) => $p->quantity.'x '.$p->product_name)->implode(', ') }}</td>
                            <td class="px-3 py-2">{{ ucfirst($record->status->value ?? '') }}</td>
                            <td class="px-3 py-2">₦{{ number_format($record->total_price, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 py-4 text-center text-gray-500">No orders found for this day.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        @endif
    </div>

    <div class="flex justify-center">
        @if($this->selectedDate !== null)
            {{ $this->dayOrders->links(data: ['scrollTo' => false]) }}
        @else
            {{ $this->dailyRecords->links(data: ['scrollTo' => false]) }}
        @endif
    </div>
</div>
