<div class="space-y-4">
    @if($agentId === null)
        <div class="flex flex-col sm:flex-row gap-3">
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Search agent..."
                class="w-full sm:w-64 px-3 py-2 text-sm border rounded-lg dark:bg-gray-800 dark:border-gray-700"
            />
        </div>

        <div class="overflow-auto max-h-[60vh]">
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-100 dark:bg-gray-800">
                    <tr>
                        <th class="px-3 py-2">Agent</th>
                        <th class="px-3 py-2">Location</th>
                        <th class="px-3 py-2">Sales</th>
                        <th class="px-3 py-2">Pending</th>
                        <th class="px-3 py-2">Sales Value</th>
                        <th class="px-3 py-2">Revenue</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->agents as $agent)
                        <tr class="border-b">
                            <td class="px-3 py-2 font-medium">{{ $agent->name }}</td>
                            <td class="px-3 py-2">{{ $agent->lga ?? $agent->state ?? '-' }}</td>
                            <td class="px-3 py-2">{{ $agent->sales_count }}</td>
                            <td class="px-3 py-2">{{ $agent->pending_count }}</td>
                            <td class="px-3 py-2">₦{{ number_format($agent->sales_value, 2) }}</td>
                            <td class="px-3 py-2 font-semibold text-green-600">₦{{ number_format($agent->revenue, 2) }}</td>
                            <td class="px-3 py-2">
                                <button
                                    type="button"
                                    wire:click="selectAgent({{ $agent->id }})"
                                    class="text-sm text-blue-600 hover:underline"
                                >
                                    View
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-3 py-4 text-center text-gray-500">No agents found in the selected period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @else
        <div class="flex items-center justify-between">
            <div>
                <button type="button" wire:click="back" class="text-sm text-blue-600 hover:underline">← Back to all agents</button>
                <h4 class="mt-1 text-lg font-semibold">{{ $this->selectedAgent?->name }}</h4>
            </div>
        </div>

        <div class="overflow-auto max-h-[60vh]">
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-100 dark:bg-gray-800">
                    <tr>
                        <th class="px-3 py-2">Date</th>
                        <th class="px-3 py-2">Customer</th>
                        <th class="px-3 py-2">Products</th>
                        <th class="px-3 py-2">Payment</th>
                        <th class="px-3 py-2">Value</th>
                        <th class="px-3 py-2">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->agentRecords as $record)
                        <tr class="border-b">
                            <td class="px-3 py-2">{{ $record->created_at->format('M d, Y H:i') }}</td>
                            <td class="px-3 py-2">{{ $record->customer_name ?? '-' }}</td>
                            <td class="px-3 py-2">{{ collect($record->products)->map(fn ($p) => $p['quantity'].'x '.$p['product_name'])->implode(', ') }}</td>
                            <td class="px-3 py-2">{{ $record->is_credit ? 'Credit' : 'Cash' }}</td>
                            <td class="px-3 py-2">₦{{ number_format((float) $record->total_value, 2) }}</td>
                            <td class="px-3 py-2">{{ $record->status }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-3 py-4 text-center text-gray-500">No sales records in the selected period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex justify-center">
            {{ $this->agentRecords->links(data: ['scrollTo' => false]) }}
        </div>
    @endif
</div>
