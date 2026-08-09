@if ($userId !== null)
    <div class="p-4">
        <livewire:rep-sales-breakdown-table :userId="$userId" :key="'rep-sales-'.$userId" />
    </div>
@else
    <div class="p-4">
        <p class="mb-2 text-sm text-gray-500 dark:text-gray-400">Choose a rep or lead to view their daily sales breakdown.</p>
        <select
            wire:change="if ($event.target.value !== '') { $dispatch('open-rep-sales-breakdown', { userId: Number($event.target.value) }); }"
            class="w-full rounded-lg border-gray-300 text-sm shadow-sm"
        >
            <option value="">Select rep / lead…</option>
            @foreach ($options as $id => $label)
                <option value="{{ $id }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>
@endif
