<div class="space-y-4">
    <div class="flex flex-col sm:flex-row gap-3">
        <input
            type="text"
            wire:model.live.debounce.300ms="search"
            placeholder="Search..."
            class="w-full sm:w-64 px-3 py-2 text-sm border rounded-lg dark:bg-gray-800 dark:border-gray-700"
        />
    </div>

    <div class="overflow-auto max-h-[60vh]">
        @if($this->type === 'stock_transfer')
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-100 dark:bg-gray-800">
                    <tr>
                        <th class="px-3 py-2">#</th>
                        <th class="px-3 py-2">Requested By</th>
                        <th class="px-3 py-2">From Warehouse</th>
                        <th class="px-3 py-2">To Agent</th>
                        <th class="px-3 py-2">Items</th>
                        <th class="px-3 py-2">Notes</th>
                        <th class="px-3 py-2">Submitted</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->records as $record)
                        <tr class="border-b">
                            <td class="px-3 py-2">#{{ $record->id }}</td>
                            <td class="px-3 py-2">{{ $record->requester?->name ?? '-' }}</td>
                            <td class="px-3 py-2">{{ $record->fromWarehouse?->name ?? '-' }}</td>
                            <td class="px-3 py-2">{{ $record->toAgent?->name ?? '-' }}</td>
                            <td class="px-3 py-2">
                                {{ $record->items->map(fn ($item) => $item->quantity.'x '.$item->productType?->name.' ('.$item->grammage.'g)')->implode(', ') }}
                            </td>
                            <td class="px-3 py-2">{{ $record->notes ?? '-' }}</td>
                            <td class="px-3 py-2">{{ $record->created_at->format('M d, Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-3 py-4 text-center text-gray-500">No pending stock transfer approvals.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        @elseif($this->type === 'stock_count')
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-100 dark:bg-gray-800">
                    <tr>
                        <th class="px-3 py-2">Agent</th>
                        <th class="px-3 py-2">Type</th>
                        <th class="px-3 py-2">Items</th>
                        <th class="px-3 py-2">Notes</th>
                        <th class="px-3 py-2">Submitted</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->records as $record)
                        <tr class="border-b">
                            <td class="px-3 py-2">{{ $record->user?->name ?? '-' }}</td>
                            <td class="px-3 py-2">
                                <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full {{ $record->is_additional_count ? 'bg-amber-100 text-amber-700' : 'bg-sky-100 text-sky-700' }}">
                                    {{ $record->is_additional_count ? 'Additional' : 'Initial' }}
                                </span>
                            </td>
                            <td class="px-3 py-2">
                                {{ $record->items->map(fn ($item) => $item->quantity.'x '.($item->productType?->name ?? $item->product_name).' ('.$item->grammage.'g)')->implode(', ') }}
                            </td>
                            <td class="px-3 py-2">{{ $record->notes ?? '-' }}</td>
                            <td class="px-3 py-2">{{ $record->created_at->format('M d, Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 py-4 text-center text-gray-500">No pending stock count approvals.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        @elseif($this->type === 'sales_records')
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-100 dark:bg-gray-800">
                    <tr>
                        <th class="px-3 py-2">Agent</th>
                        <th class="px-3 py-2">Products</th>
                        <th class="px-3 py-2">Value</th>
                        <th class="px-3 py-2">Payment</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">Submitted</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->records as $record)
                        <tr class="border-b">
                            <td class="px-3 py-2">{{ $record->agent?->name ?? '-' }}</td>
                            <td class="px-3 py-2">
                                {{ collect($record->products)->map(fn ($product) => $product['quantity'].'x '.$product['product_name'])->implode(', ') }}
                            </td>
                            <td class="px-3 py-2">₦{{ number_format((float) $record->total_value, 2) }}</td>
                            <td class="px-3 py-2">{{ $record->is_credit ? 'Credit' : 'Cash' }}</td>
                            <td class="px-3 py-2">{{ $record->status }}</td>
                            <td class="px-3 py-2">{{ $record->created_at->format('M d, Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-3 py-4 text-center text-gray-500">No pending sales record approvals.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        @else
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-100 dark:bg-gray-800">
                    <tr>
                        <th class="px-3 py-2">Returned By</th>
                        <th class="px-3 py-2">Warehouse</th>
                        <th class="px-3 py-2">Product</th>
                        <th class="px-3 py-2">Weight</th>
                        <th class="px-3 py-2">Qty</th>
                        <th class="px-3 py-2">Reason</th>
                        <th class="px-3 py-2">Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->records as $record)
                        <tr class="border-b">
                            <td class="px-3 py-2">{{ $record->user?->name ?? '-' }}</td>
                            <td class="px-3 py-2">{{ $record->warehouse?->name ?? '-' }}</td>
                            <td class="px-3 py-2">{{ $record->productType?->name ?? '-' }}</td>
                            <td class="px-3 py-2">{{ $record->grammage }}g</td>
                            <td class="px-3 py-2">{{ $record->quantity }}</td>
                            <td class="px-3 py-2">{{ $record->reason ?? '-' }}</td>
                            <td class="px-3 py-2">{{ $record->created_at->format('M d, Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-3 py-4 text-center text-gray-500">No damaged stock returns awaiting approval.</td>
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
