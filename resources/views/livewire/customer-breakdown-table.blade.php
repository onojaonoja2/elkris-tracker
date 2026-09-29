<div class="space-y-4">
    @if($level === 'groups')
        <div class="flex flex-col sm:flex-row gap-3">
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Search group..."
                class="w-full sm:w-64 px-3 py-2 text-sm border rounded-lg dark:bg-gray-800 dark:border-gray-700"
            />
        </div>

        <div class="overflow-auto max-h-[60vh]">
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-100 dark:bg-gray-800">
                    <tr>
                        <th class="px-3 py-2">Group / Agent</th>
                        <th class="px-3 py-2">Type</th>
                        <th class="px-3 py-2">Customers</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->groups as $row)
                        <tr class="border-b">
                            <td class="px-3 py-2 font-medium">{{ $row->label }}</td>
                            <td class="px-3 py-2">
                                <span class="px-2 py-0.5 text-xs rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200">
                                    {{ \App\Livewire\CustomerBreakdownTable::typeLabel($row->type) }}
                                </span>
                            </td>
                            <td class="px-3 py-2">{{ $row->count }}</td>
                            <td class="px-3 py-2 text-right">
                                @if(in_array($row->type, ['rep', 'lead']))
                                    <button type="button" wire:click="selectUser({{ $row->user_id }})" class="text-sm text-blue-600 hover:underline">
                                        View Customers
                                    </button>
                                @elseif($row->type === 'group')
                                    <button type="button" wire:click="selectGroup('{{ str_replace('group-', '', $row->key) }}')" class="text-sm text-blue-600 hover:underline">
                                        View Agents
                                    </button>
                                @else
                                    <span class="text-sm text-gray-400">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-3 py-4 text-center text-gray-500">No customers in the selected period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @elseif($level === 'agents')
        <div class="flex items-center justify-between">
            <div>
                <button type="button" wire:click="back" class="text-sm text-blue-600 hover:underline">← Back to groups</button>
                <h4 class="mt-1 text-lg font-semibold">{{ $this->currentLabel }}</h4>
            </div>
        </div>

        <div class="overflow-auto max-h-[60vh]">
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-100 dark:bg-gray-800">
                    <tr>
                        <th class="px-3 py-2">Agent</th>
                        <th class="px-3 py-2">Customers</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->agents as $agent)
                        <tr class="border-b">
                            <td class="px-3 py-2 font-medium">{{ $agent->name }}</td>
                            <td class="px-3 py-2">{{ $agent->count }}</td>
                            <td class="px-3 py-2 text-right">
                                <button type="button" wire:click="selectAgent({{ $agent->id }})" class="text-sm text-blue-600 hover:underline">
                                    View Customers
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-3 py-4 text-center text-gray-500">No agents found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @else
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div>
                <button type="button" wire:click="back" class="text-sm text-blue-600 hover:underline">← Back</button>
                <h4 class="mt-1 text-lg font-semibold">{{ $this->currentLabel }} — Customers</h4>
            </div>
            <div class="flex gap-2">
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search customers..."
                    class="w-full sm:w-56 px-3 py-2 text-sm border rounded-lg dark:bg-gray-800 dark:border-gray-700"
                />
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
                        <th class="px-3 py-2">Location</th>
                        <th class="px-3 py-2">Date Added</th>
                        <th class="px-3 py-2">Orders</th>
                        <th class="px-3 py-2">Order Value</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->customers as $customer)
                        <tr class="border-b">
                            <td class="px-3 py-2 font-medium">{{ $customer->customer_name }}</td>
                            <td class="px-3 py-2">{{ $customer->phone_number ?? '-' }}</td>
                            <td class="px-3 py-2">{{ $customer->city ?? $customer->state ?? '-' }}</td>
                            <td class="px-3 py-2">{{ $customer->created_at?->format('M d, Y') }}</td>
                            <td class="px-3 py-2">{{ $customer->orders_count }}</td>
                            <td class="px-3 py-2 font-semibold">₦{{ number_format((float) $customer->orders_total, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-3 py-4 text-center text-gray-500">No customers found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex justify-center">
            {{ $this->customers->links(data: ['scrollTo' => false]) }}
        </div>
    @endif
</div>
