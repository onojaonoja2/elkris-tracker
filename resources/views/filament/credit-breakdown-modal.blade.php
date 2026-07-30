<div class="p-4 overflow-auto max-h-[70vh]">
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
            @forelse($records as $record)
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
</div>
