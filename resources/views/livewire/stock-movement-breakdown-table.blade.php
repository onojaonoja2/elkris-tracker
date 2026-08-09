<div class="space-y-4">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h4 class="text-lg font-semibold">{{ $this->entity?->name ?? 'Entity' }}</h4>
            <p class="text-sm text-gray-500">
                {{ $this->entityType === 'agent' ? 'Agent' : 'Warehouse' }} stock movements
            </p>
        </div>
        @if($this->product !== null)
            <button
                type="button"
                wire:click="clearProductFilter"
                class="inline-flex items-center gap-1 text-sm text-primary-600 hover:text-primary-500"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
                Clear product filter
            </button>
        @endif
    </div>

    <div class="flex flex-col sm:flex-row gap-3">
        <input
            type="text"
            wire:model.live.debounce.300ms="search"
            placeholder="Search by product, reference, type or detail..."
            class="w-full sm:w-72 px-3 py-2 text-sm border rounded-lg dark:bg-gray-800 dark:border-gray-700"
        />
        <select
            wire:model.live="typeFilter"
            class="w-full sm:w-48 px-3 py-2 text-sm border rounded-lg dark:bg-gray-800 dark:border-gray-700"
        >
            <option value="all">All movements</option>
            <option value="in">Stock in</option>
            <option value="out">Stock out</option>
            <option value="count">Stock counts</option>
        </select>
    </div>

    @php
        $totals = $this->totals;
    @endphp
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
        <div class="rounded-lg bg-green-50 dark:bg-green-900/20 px-4 py-2">
            <span class="text-green-700 dark:text-green-300 font-semibold">In: +{{ number_format($totals['in']) }} pcs</span>
        </div>
        <div class="rounded-lg bg-red-50 dark:bg-red-900/20 px-4 py-2">
            <span class="text-red-700 dark:text-red-300 font-semibold">Out: -{{ number_format($totals['out']) }} pcs</span>
        </div>
        <div class="rounded-lg bg-gray-100 dark:bg-gray-800 px-4 py-2">
            <span class="text-gray-700 dark:text-gray-300 font-semibold">Net: {{ $totals['net'] >= 0 ? '+' : '' }}{{ number_format($totals['net']) }} pcs</span>
        </div>
    </div>

    <div class="overflow-auto max-h-[60vh]">
        <table class="w-full text-sm text-left">
            <thead class="bg-gray-100 dark:bg-gray-800">
                <tr>
                    <th class="px-3 py-2">Date</th>
                    <th class="px-3 py-2">Type</th>
                    <th class="px-3 py-2">Product</th>
                    <th class="px-3 py-2">Weight</th>
                    <th class="px-3 py-2 text-right">Qty</th>
                    <th class="px-3 py-2">Ref</th>
                    <th class="px-3 py-2">Status</th>
                    <th class="px-3 py-2">Details</th>
                </tr>
            </thead>
            <tbody>
                @forelse($this->rows as $row)
                    <tr class="border-b">
                        <td class="px-3 py-2">{{ $row->date->format('M d, Y') }}</td>
                        <td class="px-3 py-2">
                            <span class="inline-flex items-center gap-1">
                                @if($row->direction === 'in')
                                    <span class="text-green-600 dark:text-green-400">▲</span>
                                @elseif($row->direction === 'out')
                                    <span class="text-red-600 dark:text-red-400">▼</span>
                                @endif
                                {{ $row->type }}
                            </span>
                        </td>
                        <td class="px-3 py-2">{{ $row->product }}</td>
                        <td class="px-3 py-2">{{ $row->grammage ? $row->grammage.'g' : '-' }}</td>
                        <td class="px-3 py-2 text-right font-mono {{ $row->direction === 'in' ? 'text-green-600 dark:text-green-400' : ($row->direction === 'out' ? 'text-red-600 dark:text-red-400' : 'text-gray-500') }}">
                            {{ $row->direction === 'in' ? '+' : ($row->direction === 'out' ? '-' : '') }}{{ number_format($row->quantity) }}
                        </td>
                        <td class="px-3 py-2">{{ $row->reference }}</td>
                        <td class="px-3 py-2">{{ ucfirst($row->status) }}</td>
                        <td class="px-3 py-2">{{ $row->details }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-3 py-4 text-center text-gray-500">No stock movements found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="flex justify-center">
        {{ $this->rows->links(data: ['scrollTo' => false]) }}
    </div>
</div>
