<div class="filament-widget px-4 py-4">
    <h2 class="text-lg font-bold mb-4">Stock Levels Overview</h2>

    @php
        $totals = (object) [
            'warehouse' => 0,
            'stockist' => 0,
            'agent' => 0,
            'all' => 0,
        ];
    @endphp

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b dark:border-gray-700">
                    <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-300">Location</th>
                    <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-300">Type</th>
                    <th class="px-3 py-2 text-left font-medium text-gray-500 dark:text-gray-300">Product</th>
                    <th class="px-3 py-2 text-right font-medium text-gray-500 dark:text-gray-300">Grammage</th>
                    <th class="px-3 py-2 text-right font-medium text-gray-500 dark:text-gray-300">Quantity</th>
                </tr>
            </thead>
            <tbody>
                @forelse($this->getStockLevels() as $row)
                    @php
                        if ($row->type === 'Warehouse') $totals->warehouse += $row->quantity;
                        elseif ($row->type === 'Stockist') $totals->stockist += $row->quantity;
                        elseif ($row->type === 'Agent') $totals->agent += $row->quantity;
                        $totals->all += $row->quantity;
                    @endphp
                    <tr class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">
                        <td class="px-3 py-2">{{ $row->location }}</td>
                        <td class="px-3 py-2">
                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium
                                @if($row->type_color === 'warning') bg-warning-100 text-warning-800 dark:bg-warning-900 dark:text-warning-300
                                @elseif($row->type_color === 'info') bg-info-100 text-info-800 dark:bg-info-900 dark:text-info-300
                                @elseif($row->type_color === 'primary') bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-300
                                @elseif($row->type_color === 'success') bg-success-100 text-success-800 dark:bg-success-900 dark:text-success-300
                                @else bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300
                                @endif">
                                {{ $row->type }}
                            </span>
                        </td>
                        <td class="px-3 py-2">{{ $row->product }}</td>
                        <td class="px-3 py-2 text-right font-mono">{{ $row->grammage }}g</td>
                        <td class="px-3 py-2 text-right font-mono">{{ number_format($row->quantity) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-3 py-4 text-center text-gray-500">No stock data available.</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="border-t-2 border-gray-300 dark:border-gray-600 font-semibold">
                    <td colspan="4" class="px-3 py-3 text-right text-gray-700 dark:text-gray-300">Warehouse Total:</td>
                    <td class="px-3 py-3 text-right font-mono">{{ number_format($totals->warehouse) }}</td>
                </tr>
                <tr class="font-semibold">
                    <td colspan="4" class="px-3 py-1 text-right text-gray-700 dark:text-gray-300">Stockist Total:</td>
                    <td class="px-3 py-1 text-right font-mono">{{ number_format($totals->stockist) }}</td>
                </tr>
                <tr class="font-semibold">
                    <td colspan="4" class="px-3 py-1 text-right text-gray-700 dark:text-gray-300">Agent Total:</td>
                    <td class="px-3 py-1 text-right font-mono">{{ number_format($totals->agent) }}</td>
                </tr>
                <tr class="border-t border-gray-300 dark:border-gray-600 font-bold text-lg">
                    <td colspan="4" class="px-3 py-3 text-right text-gray-900 dark:text-gray-100">All Stock Total:</td>
                    <td class="px-3 py-3 text-right font-mono">{{ number_format($totals->all) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
