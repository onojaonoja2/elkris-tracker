<div class="space-y-4">
    <div class="grid grid-cols-2 gap-4 text-sm">
        <div>
            <p class="text-gray-500 dark:text-gray-400">Transfer #{{ $transfer->id }}</p>
            <p class="text-gray-500 dark:text-gray-400">
                From: {{ $transfer->fromWarehouse?->name ?? $transfer->fromAgent?->name ?? 'N/A' }}
            </p>
        </div>
        <div>
            <p class="text-gray-500 dark:text-gray-400">
                Received By: {{ $transfer->receiver?->name ?? 'N/A' }}
            </p>
            <p class="text-gray-500 dark:text-gray-400">
                Received At: {{ $transfer->received_at ? $transfer->received_at->format('M d, Y H:i') : 'N/A' }}
            </p>
        </div>
    </div>

    <div class="overflow-auto max-h-[60vh]">
        <table class="w-full text-sm text-left">
            <thead class="bg-gray-100 dark:bg-gray-800">
                <tr>
                    <th class="px-3 py-2">Product</th>
                    <th class="px-3 py-2">Weight</th>
                    <th class="px-3 py-2">Dispatched</th>
                    <th class="px-3 py-2">Accepted</th>
                    <th class="px-3 py-2">Rejected</th>
                    <th class="px-3 py-2">Rejection Reason</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transfer->items as $item)
                    <tr class="border-b">
                        <td class="px-3 py-2">{{ $item->productType?->name ?? 'N/A' }}</td>
                        <td class="px-3 py-2">{{ $item->grammage }}g</td>
                        <td class="px-3 py-2">{{ $item->quantity }}</td>
                        <td class="px-3 py-2">{{ max(0, $item->quantity - $item->rejected_quantity) }}</td>
                        <td class="px-3 py-2">
                            @if($item->rejected_quantity > 0)
                                <span class="text-danger-600 font-medium">{{ $item->rejected_quantity }}</span>
                            @else
                                0
                            @endif
                        </td>
                        <td class="px-3 py-2">{{ $item->rejection_reason ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-3 py-4 text-center text-gray-500">No items found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
