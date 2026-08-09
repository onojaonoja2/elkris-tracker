<div class="space-y-4">
    <div class="flex flex-col sm:flex-row gap-3">
        <input
            type="text"
            wire:model.live.debounce.300ms="search"
            placeholder="Search by agent, customer or order #..."
            class="w-full sm:w-64 px-3 py-2 text-sm border rounded-lg dark:bg-gray-800 dark:border-gray-700"
        />

        <select
            wire:model.live="statusFilter"
            class="w-full sm:w-48 px-3 py-2 text-sm border rounded-lg dark:bg-gray-800 dark:border-gray-700"
        >
            <option value="">All statuses</option>
            @foreach($this->statusOptions as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="overflow-auto max-h-[60vh]">
        @if($type === 'credit')
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-100 dark:bg-gray-800">
                    <tr>
                        <th class="px-3 py-2">Agent</th>
                        <th class="px-3 py-2">Customer</th>
                        <th class="px-3 py-2">Value</th>
                        <th class="px-3 py-2">Expected Date</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">Created</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->records as $record)
                        <tr class="border-b">
                            <td class="px-3 py-2">{{ $record->agent?->name ?? 'N/A' }}</td>
                            <td class="px-3 py-2">{{ $record->customer_name ?? '-' }}</td>
                            <td class="px-3 py-2">₦{{ number_format($record->total_value, 2) }}</td>
                            <td class="px-3 py-2">{{ $record->expected_collection_date?->format('M d, Y') ?? '-' }}</td>
                            <td class="px-3 py-2">{{ str_replace('_', ' ', ucfirst($record->credit_status)) }}</td>
                            <td class="px-3 py-2">{{ $record->created_at->format('M d, Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-3 py-4 text-center text-gray-500">No records found.</td>
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
                        <th class="px-3 py-2">Submitted By</th>
                        <th class="px-3 py-2">Value</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->records as $record)
                        <tr class="border-b">
                            <td class="px-3 py-2">#{{ $record->id }}</td>
                            <td class="px-3 py-2">{{ $record->customer?->customer_name ?? '-' }}</td>
                            <td class="px-3 py-2">{{ $record->user?->name ?? '-' }}</td>
                            <td class="px-3 py-2">₦{{ number_format($record->total_price, 2) }}</td>
                            <td class="px-3 py-2">{{ str_replace('_', ' ', ucfirst($record->status->value)) }}</td>
                            <td class="px-3 py-2">{{ $record->created_at->format('M d, Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-3 py-4 text-center text-gray-500">No records found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        @endif
    </div>

    <div class="flex justify-center">
        {{ $this->records->links(data: ['scrollTo' => false]) }}
    </div>
</div>
