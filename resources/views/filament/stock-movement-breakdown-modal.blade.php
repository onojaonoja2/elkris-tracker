@if ($entityId !== null)
    <div class="p-4">
        <livewire:stock-movement-breakdown-table
            :entity-type="$entityType"
            :entity-id="$entityId"
            :product="$product"
            :grammage="$grammage"
            :key="'stock-'.$entityType.'-'.$entityId"
        />
    </div>
@else
    <div class="p-4">
        <p class="mb-2 text-sm text-gray-500 dark:text-gray-400">Select an agent or warehouse to view their stock movement history. Totals reflect dispatched / received / collected transfers, approved damaged returns and held-stock sales within the dashboard date range.</p>
        <livewire:stock-entity-breakdown-table :key="'stock-entity-'.auth()->id()" />
    </div>
@endif
