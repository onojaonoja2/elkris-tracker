<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900">Database Table Management</h2>
                <p class="text-sm text-gray-500">Use with caution — clearing tables is irreversible.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="text-left py-3 px-4 font-medium text-gray-600">Table Name</th>
                            <th class="text-right py-3 px-4 font-medium text-gray-600">Records</th>
                            <th class="text-right py-3 px-4 font-medium text-gray-600">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->getTableData() as $table)
                        <tr class="border-b border-gray-100 hover:bg-gray-50">
                            <td class="py-2 px-4 font-mono text-sm">
                                {{ $table['name'] }}
                                @if(!$table['can_clear'])
                                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">protected</span>
                                @endif
                            </td>
                            <td class="py-2 px-4 text-right">{{ number_format($table['records']) }}</td>
                            <td class="py-2 px-4 text-right">
                                @if($table['can_clear'] && $table['records'] > 0)
                                <button
                                    wire:click="clearTable('{{ $table['name'] }}')"
                                    wire:confirm="Are you sure you want to clear ALL data from '{{ $table['name'] }}'? This cannot be undone."
                                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md text-red-700 bg-red-50 hover:bg-red-100 border border-red-200"
                                >
                                    Clear Table
                                </button>
                                @else
                                <span class="text-xs text-gray-400">—</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
