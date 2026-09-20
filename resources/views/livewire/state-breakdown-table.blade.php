<div class="space-y-4">
    <div class="flex flex-col sm:flex-row gap-3">
        <input
            type="text"
            wire:model.live.debounce.300ms="search"
            placeholder="Search records..."
            class="w-full sm:w-64 px-3 py-2 text-sm border rounded-lg dark:bg-gray-800 dark:border-gray-700"
        />

        <button
            type="button"
            wire:click="exportCsv"
            class="whitespace-nowrap inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700"
        >
            Export
        </button>
    </div>

    <div class="overflow-auto max-h-[60vh]">
        @if($entity === 'people')
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-100 dark:bg-gray-800">
                    <tr>
                        <th class="px-3 py-2">Name</th>
                        <th class="px-3 py-2">Role</th>
                        <th class="px-3 py-2">Phone</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">LGA</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->records as $record)
                        <tr class="border-b">
                            <td class="px-3 py-2">
                                <div class="font-medium">{{ $record->name }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $record->email }}</div>
                            </td>
                            <td class="px-3 py-2">{{ str_replace('_', ' ', ucfirst($record->role)) }}</td>
                            <td class="px-3 py-2">{{ $record->phone ?? '-' }}</td>
                            <td class="px-3 py-2">{{ $record->is_active ? 'Active' : 'Inactive' }}</td>
                            <td class="px-3 py-2">{{ $record->lga?->name ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 py-4 text-center text-gray-500">No records found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        @else
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-100 dark:bg-gray-800">
                    <tr>
                        <th class="px-3 py-2">Record #</th>
                        <th class="px-3 py-2">Agent</th>
                        <th class="px-3 py-2">Customer</th>
                        <th class="px-3 py-2">Value</th>
                        <th class="px-3 py-2">{{ $entity === 'credit' ? 'Credit Status' : 'Status' }}</th>
                        <th class="px-3 py-2">Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->records as $record)
                        <tr class="border-b">
                            <td class="px-3 py-2">#{{ $record->id }}</td>
                            <td class="px-3 py-2">{{ $record->agent?->name ?? 'N/A' }}</td>
                            <td class="px-3 py-2">{{ $record->customer_name ?? '-' }}</td>
                            <td class="px-3 py-2">₦{{ number_format($record->total_value, 2) }}</td>
                            <td class="px-3 py-2">
                                {{ $entity === 'credit' ? str_replace('_', ' ', ucfirst($record->credit_status ?? '')) : str_replace('_', ' ', ucfirst($record->status)) }}
                            </td>
                            <td class="px-3 py-2">{{ $record->created_at?->format('M d, Y H:i') }}</td>
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
