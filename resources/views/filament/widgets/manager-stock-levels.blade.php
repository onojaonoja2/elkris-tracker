<div class="filament-widget px-4 py-4">
    <h2 class="text-lg font-bold mb-4">Stock Levels Overview</h2>

    @php
        $grandTotal = 0;
    @endphp

    <div class="overflow-x-auto">
        <table class="w-full text-sm border-collapse">
            <thead>
                <tr class="border-b-2 border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                    <th class="px-3 py-2.5 text-left font-semibold text-gray-600 dark:text-gray-300">Location</th>
                    <th class="px-3 py-2.5 text-left font-semibold text-gray-600 dark:text-gray-300">Type</th>
                    <th class="px-3 py-2.5 text-left font-semibold text-gray-600 dark:text-gray-300">Product</th>
                    <th class="px-3 py-2.5 text-right font-semibold text-gray-600 dark:text-gray-300">Grammage</th>
                    <th class="px-3 py-2.5 text-right font-semibold text-gray-600 dark:text-gray-300">Cartons</th>
                    <th class="px-3 py-2.5 text-right font-semibold text-gray-600 dark:text-gray-300">Quantity</th>
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
                            $groupTotal = $groupData->sum('quantity');
                            $grandTotal += $groupTotal;
                        @endphp
                        <tr class="bg-gray-100 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                            <td colspan="4" class="px-3 py-2 font-bold text-sm text-gray-700 dark:text-gray-200">
                                {{ $groupName }}
                            </td>
                            <td colspan="2" class="px-3 py-2 text-right font-mono text-sm font-bold text-gray-700 dark:text-gray-200">
                                {{ number_format($groupTotal) }}
                            </td>
                        </tr>
                        @foreach($groupData as $row)
                            <tr class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 {{ $loop->even ? 'bg-gray-25 dark:bg-gray-850' : '' }}">
                                <td class="px-3 py-2 pl-6">{{ $row->location }}</td>
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
                                <td class="px-3 py-2 text-right font-mono text-sm">{{ number_format($row->cartons) }} ctns + {{ number_format($row->remaining_pieces) }} pcs</td>
                                <td class="px-3 py-2 text-right font-mono text-sm font-semibold">{{ number_format($row->quantity) }}</td>
                            </tr>
                        @endforeach
                    @elseif($groupData instanceof \Illuminate\Support\Collection && $groupData->isNotEmpty() && $groupData->first() instanceof \Illuminate\Support\Collection)
                        {{-- Nested group (Region → State → rows) --}}
                        @php
                            $groupTotal = 0;
                            foreach ($groupData as $regionName => $states) {
                                foreach ($states as $stateName => $rows) {
                                    $groupTotal += $rows->sum('quantity');
                                }
                            }
                            $grandTotal += $groupTotal;
                        @endphp
                        <tr class="bg-gray-100 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                            <td colspan="4" class="px-3 py-2 font-bold text-sm text-gray-700 dark:text-gray-200">
                                {{ $groupName }}
                            </td>
                            <td colspan="2" class="px-3 py-2 text-right font-mono text-sm font-bold text-gray-700 dark:text-gray-200">
                                {{ number_format($groupTotal) }}
                            </td>
                        </tr>
                        @foreach($groupData as $regionName => $states)
                            @php
                                $regionTotal = 0;
                                foreach ($states as $rows) {
                                    $regionTotal += $rows->sum('quantity');
                                }
                            @endphp
                            <tr class="bg-blue-50 dark:bg-blue-900/30 border-b border-blue-100 dark:border-blue-800">
                                <td colspan="4" class="px-3 py-1.5 pl-6 font-semibold text-xs text-blue-700 dark:text-blue-300 uppercase tracking-wide">
                                    {{ $regionName }}
                                </td>
                                <td colspan="2" class="px-3 py-1.5 text-right font-mono text-xs font-semibold text-blue-700 dark:text-blue-300">
                                    {{ number_format($regionTotal) }}
                                </td>
                            </tr>
                            @foreach($states as $stateName => $rows)
                                <tr class="bg-green-50/50 dark:bg-green-900/20 border-b border-green-50 dark:border-green-900/30">
                                    <td colspan="4" class="px-3 py-1.5 pl-10 font-medium text-xs text-green-700 dark:text-green-400">
                                        {{ $stateName }}
                                    </td>
                                    <td colspan="2" class="px-3 py-1.5 text-right font-mono text-xs font-medium text-green-700 dark:text-green-400">
                                        {{ number_format($rows->sum('quantity')) }}
                                    </td>
                                </tr>
                                @foreach($rows as $row)
                                    <tr class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 {{ $loop->even ? 'bg-gray-25 dark:bg-gray-850' : '' }}">
                                        <td class="px-3 py-2 pl-14">{{ $row->location }}</td>
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
                                        <td class="px-3 py-2 text-right font-mono text-sm">{{ number_format($row->cartons) }} ctns + {{ number_format($row->remaining_pieces) }} pcs</td>
                                        <td class="px-3 py-2 text-right font-mono text-sm font-semibold">{{ number_format($row->quantity) }}</td>
                                    </tr>
                                @endforeach
                            @endforeach
                        @endforeach
                    @endif
                @empty
                    <tr>
                        <td colspan="6" class="px-3 py-6 text-center text-gray-500">No stock data available.</td>
                    </tr>
                @endforelse
            </tbody>
            @if($grandTotal > 0)
                <tfoot>
                    <tr class="border-t-2 border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 font-bold">
                        <td colspan="4" class="px-3 py-3 text-right text-gray-700 dark:text-gray-300">Grand Total:</td>
                        <td colspan="2" class="px-3 py-3 text-right font-mono text-sm">{{ number_format($grandTotal) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
