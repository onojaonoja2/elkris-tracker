<div class="space-y-4">
    <div class="flex flex-col sm:flex-row gap-3">
        <input
            type="text"
            wire:model.live.debounce.300ms="search"
            placeholder="Search rep or lead..."
            class="w-full sm:w-64 px-3 py-2 text-sm border rounded-lg dark:bg-gray-800 dark:border-gray-700"
        />
    </div>

    <div class="overflow-auto max-h-[60vh]">
        <table class="w-full text-sm text-left">
            <thead class="bg-gray-100 dark:bg-gray-800">
                <tr>
                    <th class="px-3 py-2">Name</th>
                    <th class="px-3 py-2">Role</th>
                    <th class="px-3 py-2">Team Lead</th>
                    <th class="px-3 py-2 text-right">Orders</th>
                    <th class="px-3 py-2 text-right">Total Sales</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($this->rows as $row)
                    <tr class="border-b">
                        <td class="px-3 py-2 font-medium">{{ $row['name'] }}</td>
                        <td class="px-3 py-2">{{ $row['role'] === 'lead' ? 'Team Lead' : 'Rep' }}</td>
                        <td class="px-3 py-2">{{ $row['lead_name'] ?? '-' }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format($row['order_count']) }}</td>
                        <td class="px-3 py-2 text-right font-semibold">₦{{ number_format($row['total_sales'], 2) }}</td>
                        <td class="px-3 py-2 text-right">
                            <button
                                type="button"
                                wire:click="$dispatch('open-rep-sales-breakdown', { userId: {{ $row['id'] }} })"
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
                        <td colspan="6" class="px-3 py-4 text-center text-gray-500">No reps or leads found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="flex justify-center">
        {{ $this->rows->links(data: ['scrollTo' => false]) }}
    </div>
</div>
