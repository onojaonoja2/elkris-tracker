<div class="space-y-4">
    <div class="flex flex-col sm:flex-row gap-3">
        <input
            type="text"
            wire:model.live.debounce.300ms="search"
            placeholder="Search agent or warehouse..."
            class="w-full sm:w-64 px-3 py-2 text-sm border rounded-lg dark:bg-gray-800 dark:border-gray-700"
        />
        <select
            wire:model.live="typeFilter"
            class="w-full sm:w-48 px-3 py-2 text-sm border rounded-lg dark:bg-gray-800 dark:border-gray-700"
        >
            <option value="all">All entities</option>
            <option value="agent">Agents</option>
            <option value="warehouse">Warehouses</option>
        </select>
    </div>

    <div class="overflow-auto max-h-[60vh]">
        <table class="w-full text-sm text-left">
            <thead class="bg-gray-100 dark:bg-gray-800">
                <tr>
                    <th class="px-3 py-2">Entity</th>
                    <th class="px-3 py-2">Type</th>
                    <th class="px-3 py-2 text-right">Stock In</th>
                    <th class="px-3 py-2 text-right">Stock Out</th>
                    <th class="px-3 py-2 text-right">Net</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($this->rows as $row)
                    <tr class="border-b">
                        <td class="px-3 py-2 font-medium">{{ $row->name }}</td>
                        <td class="px-3 py-2">
                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium
                                @if($row->type_color === 'warning') bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-300
                                @else bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300
                                @endif">
                                {{ $row->type }}
                            </span>
                        </td>
                        <td class="px-3 py-2 text-right font-mono text-green-600 dark:text-green-400">+{{ number_format($row->in_total) }}</td>
                        <td class="px-3 py-2 text-right font-mono text-red-600 dark:text-red-400">-{{ number_format($row->out_total) }}</td>
                        <td class="px-3 py-2 text-right font-mono font-semibold {{ $row->net >= 0 ? 'text-gray-700 dark:text-gray-200' : 'text-red-600 dark:text-red-400' }}">
                            {{ $row->net >= 0 ? '+' : '' }}{{ number_format($row->net) }}
                        </td>
                        <td class="px-3 py-2 text-right">
                            <button
                                type="button"
                                wire:click="$dispatch('open-stock-movement-breakdown', {{ json_encode(['entityType' => $row->type === 'Agent' ? 'agent' : 'warehouse', 'entityId' => $row->id]) }})"
                                class="inline-flex items-center gap-1 text-sm text-primary-600 hover:text-primary-500"
                            >
                                View
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                </svg>
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-3 py-4 text-center text-gray-500">No agents or warehouses found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="flex justify-center">
        {{ $this->rows->links(data: ['scrollTo' => false]) }}
    </div>
</div>