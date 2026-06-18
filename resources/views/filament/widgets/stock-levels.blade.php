<div class="filament-widget px-4 py-4">
    <h2 class="text-lg font-bold mb-4">Stock Levels Overview</h2>

    @php
        $grandQuantity = 0;
        $grandCartons = 0;
    @endphp

    <div class="overflow-x-auto">
        <table class="w-full text-sm border-collapse">
            <thead>
                <tr class="border-b-2 border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                    <th class="px-3 py-2.5 text-left font-semibold text-gray-600 dark:text-gray-300">Location</th>
                    <th class="px-3 py-2.5 text-left font-semibold text-gray-600 dark:text-gray-300">Type</th>
                    <th class="px-3 py-2.5 text-left font-semibold text-gray-600 dark:text-gray-300">Product</th>
                    <th class="px-3 py-2.5 text-right font-semibold text-gray-600 dark:text-gray-300">Weight</th>
                    <th class="px-3 py-2.5 text-right font-semibold text-gray-600 dark:text-gray-300">Carton Size</th>
                    <th class="px-3 py-2.5 text-right font-semibold text-gray-600 dark:text-gray-300">Pieces</th>
                    <th class="px-3 py-2.5 text-right font-semibold text-gray-600 dark:text-gray-300">Cartons</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $groupedStock = $this->getStockLevels();
                @endphp

                @forelse($groupedStock as $groupName => $groupData)
                    @if($groupData instanceof \Illuminate\Support\Collection && $groupData->isNotEmpty() && !($groupData->first() instanceof \Illuminate\Support\Collection))
                        {{-- Flat group (Warehouse) --}}
                        @php
                            $groupQuantity = $groupData->sum('quantity');
                            $groupCartons = $groupData->sum('cartons');
                            $grandQuantity += $groupQuantity;
                            $grandCartons += $groupCartons;
                        @endphp
                        <tr class="bg-gray-100 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                            <td colspan="5" class="px-3 py-2 font-bold text-sm text-gray-700 dark:text-gray-200">
                                {{ $groupName }}
                            </td>
                            <td class="px-3 py-2 text-right font-mono text-sm font-bold text-gray-700 dark:text-gray-200">
                                {{ number_format($groupQuantity) }} pcs
                            </td>
                            <td class="px-3 py-2 text-right font-mono text-sm font-bold text-gray-700 dark:text-gray-200">
                                {{ number_format($groupCartons) }} ctns
                            </td>
                        </tr>
                        @foreach($groupData as $row)
                            <tr class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 {{ $loop->even ? 'bg-gray-25 dark:bg-gray-850' : '' }}">
                                <td class="px-3 py-2 pl-6 font-medium">{{ $row->location }}</td>
                                <td class="px-3 py-2">
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium
                                        @if($row->type_color === 'warning') bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-300
                                        @elseif($row->type_color === 'info') bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300
                                        @elseif($row->type_color === 'success') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300
                                        @else bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300
                                        @endif">
                                        {{ $row->type }}
                                    </span>
                                </td>
                                <td class="px-3 py-2">{{ $row->product }}</td>
                                <td class="px-3 py-2 text-right font-mono text-sm">{{ number_format($row->grammage) }}g</td>
                                <td class="px-3 py-2 text-right font-mono text-sm text-gray-500">{{ $row->carton_quantity }} pcs</td>
                                <td class="px-3 py-2 text-right font-mono text-sm font-semibold">{{ number_format($row->quantity) }}</td>
                                <td class="px-3 py-2 text-right font-mono text-sm">
                                    @if($row->cartons > 0)
                                        <span class="font-semibold">{{ $row->cartons }}</span>
                                    @endif
                                    @if($row->remaining_pieces > 0)
                                        <span class="text-gray-500">+{{ $row->remaining_pieces }}</span>
                                    @endif
                                    @if($row->cartons == 0 && $row->remaining_pieces == 0)
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @elseif($groupData instanceof \Illuminate\Support\Collection && $groupData->isNotEmpty() && $groupData->first() instanceof \Illuminate\Support\Collection)
                        {{-- Nested group (Region → State → rows) --}}
                        @php
                            $groupQuantity = 0;
                            $groupCartons = 0;
                            foreach ($groupData as $regionName => $states) {
                                foreach ($states as $stateName => $rows) {
                                    $groupQuantity += $rows->sum('quantity');
                                    $groupCartons += $rows->sum('cartons');
                                }
                            }
                            $grandQuantity += $groupQuantity;
                            $grandCartons += $groupCartons;
                        @endphp
                        <tr class="bg-gray-100 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                            <td colspan="5" class="px-3 py-2 font-bold text-sm text-gray-700 dark:text-gray-200">
                                {{ $groupName }}
                            </td>
                            <td class="px-3 py-2 text-right font-mono text-sm font-bold text-gray-700 dark:text-gray-200">
                                {{ number_format($groupQuantity) }} pcs
                            </td>
                            <td class="px-3 py-2 text-right font-mono text-sm font-bold text-gray-700 dark:text-gray-200">
                                {{ number_format($groupCartons) }} ctns
                            </td>
                        </tr>
                        @foreach($groupData as $regionName => $states)
                            @php
                                $regionQuantity = 0;
                                $regionCartons = 0;
                                foreach ($states as $rows) {
                                    $regionQuantity += $rows->sum('quantity');
                                    $regionCartons += $rows->sum('cartons');
                                }
                            @endphp
                            <tr class="bg-blue-50 dark:bg-blue-900/30 border-b border-blue-100 dark:border-blue-800">
                                <td colspan="5" class="px-3 py-1.5 pl-6 font-semibold text-xs text-blue-700 dark:text-blue-300 uppercase tracking-wide">
                                    {{ $regionName }}
                                </td>
                                <td class="px-3 py-1.5 text-right font-mono text-xs font-semibold text-blue-700 dark:text-blue-300">
                                    {{ number_format($regionQuantity) }} pcs
                                </td>
                                <td class="px-3 py-1.5 text-right font-mono text-xs font-semibold text-blue-700 dark:text-blue-300">
                                    {{ number_format($regionCartons) }} ctns
                                </td>
                            </tr>
                            @foreach($states as $stateName => $rows)
                                @php
                                    $stateQuantity = $rows->sum('quantity');
                                    $stateCartons = $rows->sum('cartons');
                                @endphp
                                <tr class="bg-green-50/50 dark:bg-green-900/20 border-b border-green-50 dark:border-green-900/30">
                                    <td colspan="5" class="px-3 py-1.5 pl-10 font-medium text-xs text-green-700 dark:text-green-400">
                                        {{ $stateName }}
                                    </td>
                                    <td class="px-3 py-1.5 text-right font-mono text-xs font-medium text-green-700 dark:text-green-400">
                                        {{ number_format($stateQuantity) }} pcs
                                    </td>
                                    <td class="px-3 py-1.5 text-right font-mono text-xs font-medium text-green-700 dark:text-green-400">
                                        {{ number_format($stateCartons) }} ctns
                                    </td>
                                </tr>
                                @foreach($rows as $row)
                                    <tr class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 {{ $loop->even ? 'bg-gray-25 dark:bg-gray-850' : '' }}">
                                        <td class="px-3 py-2 pl-14 font-medium">{{ $row->location }}</td>
                                        <td class="px-3 py-2">
                                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium
                                                @if($row->type_color === 'warning') bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-300
                                                @elseif($row->type_color === 'info') bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300
                                                @elseif($row->type_color === 'success') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300
                                                @else bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300
                                                @endif">
                                                {{ $row->type }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2">{{ $row->product }}</td>
                                        <td class="px-3 py-2 text-right font-mono text-sm">{{ number_format($row->grammage) }}g</td>
                                        <td class="px-3 py-2 text-right font-mono text-sm text-gray-500">{{ $row->carton_quantity }} pcs</td>
                                        <td class="px-3 py-2 text-right font-mono text-sm font-semibold">{{ number_format($row->quantity) }}</td>
                                        <td class="px-3 py-2 text-right font-mono text-sm">
                                            @if($row->cartons > 0)
                                                <span class="font-semibold">{{ $row->cartons }}</span>
                                            @endif
                                            @if($row->remaining_pieces > 0)
                                                <span class="text-gray-500">+{{ $row->remaining_pieces }}</span>
                                            @endif
                                            @if($row->cartons == 0 && $row->remaining_pieces == 0)
                                                <span class="text-gray-400">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            @endforeach
                        @endforeach
                    @endif
                @empty
                    <tr>
                        <td colspan="7" class="px-3 py-6 text-center text-gray-500">No stock data available.</td>
                    </tr>
                @endforelse
            </tbody>
            @if($grandQuantity > 0)
                <tfoot>
                    <tr class="border-t-2 border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 font-bold">
                        <td colspan="5" class="px-3 py-3 text-right text-gray-700 dark:text-gray-300">Grand Total:</td>
                        <td class="px-3 py-3 text-right font-mono text-sm">{{ number_format($grandQuantity) }} pcs</td>
                        <td class="px-3 py-3 text-right font-mono text-sm">{{ number_format($grandCartons) }} ctns</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
