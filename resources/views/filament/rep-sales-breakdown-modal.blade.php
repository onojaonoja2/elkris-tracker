@if ($userId !== null)
    <div class="p-4">
        <livewire:rep-sales-breakdown-table :userId="$userId" :key="'rep-sales-'.$userId" />
    </div>
@else
    <div class="p-4">
        <p class="mb-2 text-sm text-gray-500 dark:text-gray-400">Select a rep or lead to view their sales breakdown. Totals reflect delivered / confirmed / completed orders within the dashboard date range.</p>
        <livewire:rep-lead-entity-breakdown-table :key="'rep-lead-entity-'.auth()->id()" />
    </div>
@endif
