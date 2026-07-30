<div class="p-4 overflow-auto max-h-[70vh]">
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
            @forelse($records as $record)
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
</div>
