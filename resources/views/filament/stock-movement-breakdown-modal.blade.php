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
    <div class="p-4 space-y-4">
        <div>
            <p class="mb-2 text-sm text-gray-500 dark:text-gray-400">Choose an agent to view their stock movement history.</p>
            <select
                wire:change="if ($event.target.value !== '') { $dispatch('open-stock-movement-breakdown', { entityType: 'agent', entityId: Number($event.target.value) }); }"
                class="w-full rounded-lg border-gray-300 text-sm shadow-sm"
            >
                <option value="">Select agent…</option>
                @foreach ($agents as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <p class="mb-2 text-sm text-gray-500 dark:text-gray-400">Choose a warehouse to view its stock movement history.</p>
            <select
                wire:change="if ($event.target.value !== '') { $dispatch('open-stock-movement-breakdown', { entityType: 'warehouse', entityId: Number($event.target.value) }); }"
                class="w-full rounded-lg border-gray-300 text-sm shadow-sm"
            >
                <option value="">Select warehouse…</option>
                @foreach ($warehouses as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>
    </div>
@endif
