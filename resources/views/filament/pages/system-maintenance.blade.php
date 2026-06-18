<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Tab Navigation --}}
        <div class="flex items-center gap-1 border-b border-gray-200 dark:border-gray-700">
            <button
                wire:click="switchTab('overview')"
                class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors
                    {{ $activeTab === 'overview'
                        ? 'border-primary-500 text-primary-600'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
            >
                <x-heroicon-o-table-cells class="w-4 h-4 inline-block mr-1" />
                Overview
            </button>
            <button
                wire:click="switchTab('browse')"
                class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors
                    {{ $activeTab === 'browse'
                        ? 'border-primary-500 text-primary-600'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
            >
                <x-heroicon-o-magnifying-glass class="w-4 h-4 inline-block mr-1" />
                Browse Data
            </button>
            <button
                wire:click="switchTab('structure')"
                class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors
                    {{ $activeTab === 'structure'
                        ? 'border-primary-500 text-primary-600'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
            >
                <x-heroicon-o-cog-6-tooth class="w-4 h-4 inline-block mr-1" />
                Structure
            </button>
            <button
                wire:click="switchTab('sql')"
                class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors
                    {{ $activeTab === 'sql'
                        ? 'border-primary-500 text-primary-600'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
            >
                <x-heroicon-o-code-bracket class="w-4 h-4 inline-block mr-1" />
                SQL Query
            </button>
        </div>

        {{-- OVERVIEW TAB --}}
        @if($activeTab === 'overview')
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6" x-data>
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900">Database Tables</h2>
                <div class="flex items-center gap-2">
                    <span class="text-sm text-gray-500">{{ count($this->getTableList()) }} tables</span>
                    <button
                        wire:click="truncateAllData()"
                        wire:confirm="This will DELETE all data from non-protected tables. Are you absolutely sure?"
                        class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md text-red-700 bg-red-50 hover:bg-red-100 border border-red-200"
                    >
                        <x-heroicon-o-trash class="w-3.5 h-3.5 mr-1" />
                        Clear All Data
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="text-left py-3 px-4 font-medium text-gray-600">Table Name</th>
                            <th class="text-right py-3 px-4 font-medium text-gray-600">Records</th>
                            <th class="text-right py-3 px-4 font-medium text-gray-600">Size</th>
                            <th class="text-center py-3 px-4 font-medium text-gray-600">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->getTableList() as $table)
                        <tr class="border-b border-gray-100 hover:bg-gray-50">
                            <td class="py-2 px-4 font-mono text-sm font-medium text-gray-900">
                                {{ $table['name'] }}
                                @if(in_array($table['name'], $this->getProtectedTables()))
                                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">protected</span>
                                @endif
                            </td>
                            <td class="py-2 px-4 text-right text-gray-600">{{ number_format($table['records']) }}</td>
                            <td class="py-2 px-4 text-right text-gray-500 text-xs">{{ $table['size'] }}</td>
                            <td class="py-2 px-4 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <button
                                        wire:click="browseTable('{{ $table['name'] }}')"
                                        class="inline-flex items-center px-2 py-1 text-xs font-medium rounded text-blue-700 bg-blue-50 hover:bg-blue-100 border border-blue-200"
                                        title="Browse Data"
                                    >
                                        <x-heroicon-o-magnifying-glass class="w-3 h-3" />
                                    </button>
                                    <button
                                        wire:click="viewStructure('{{ $table['name'] }}')"
                                        class="inline-flex items-center px-2 py-1 text-xs font-medium rounded text-gray-700 bg-gray-50 hover:bg-gray-100 border border-gray-200"
                                        title="View Structure"
                                    >
                                        <x-heroicon-o-cog-6-tooth class="w-3 h-3" />
                                    </button>
                                    @if(!in_array($table['name'], $this->getProtectedTables()) && $table['records'] > 0)
                                    <button
                                        wire:click="clearTable('{{ $table['name'] }}')"
                                        wire:confirm="Are you sure you want to clear ALL data from '{{ $table['name'] }}'? This cannot be undone."
                                        class="inline-flex items-center px-2 py-1 text-xs font-medium rounded text-red-700 bg-red-50 hover:bg-red-100 border border-red-200"
                                        title="Clear Table"
                                    >
                                        <x-heroicon-o-trash class="w-3 h-3" />
                                    </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- BROWSE TAB --}}
        @if($activeTab === 'browse')
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-3">
                    <h2 class="text-lg font-semibold text-gray-900">
                        @if($selectedTable)
                            Browsing: <span class="font-mono text-primary-600">{{ $selectedTable }}</span>
                        @else
                            Select a Table to Browse
                        @endif
                    </h2>
                    @if($selectedTable)
                        @php $browseData = $this->getBrowseData(); @endphp
                        <span class="text-sm text-gray-500">({{ number_format($browseData['total']) }} rows)</span>
                    @endif
                </div>
            </div>

            @if(! $selectedTable)
            <div class="text-center py-12 text-gray-500">
                <x-heroicon-o-table-cells class="w-12 h-12 mx-auto mb-3 text-gray-300" />
                <p>Select a table from the Overview tab to browse its data.</p>
                <button
                    wire:click="switchTab('overview')"
                    class="mt-3 inline-flex items-center px-4 py-2 text-sm font-medium text-primary-600 hover:text-primary-700"
                >
                    ← Go to Overview
                </button>
            </div>
            @else
                @php $browseData = $this->getBrowseData(); @endphp

                @if(empty($browseData['rows']))
                <div class="text-center py-12 text-gray-500">
                    <p>This table is empty.</p>
                </div>
                @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm border border-gray-200 rounded-lg">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200">
                                <th class="py-2 px-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-12">#</th>
                                @foreach($browseData['columns'] as $col)
                                <th class="py-2 px-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                    <button
                                        wire:click="sortBrowse('{{ $col }}')"
                                        class="hover:text-primary-600 flex items-center gap-1"
                                    >
                                        {{ $col }}
                                        @if($sortColumn === $col)
                                            @if($sortDirection === 'ASC')
                                                <x-heroicon-o-chevron-up class="w-3 h-3" />
                                            @else
                                                <x-heroicon-o-chevron-down class="w-3 h-3" />
                                            @endif
                                        @endif
                                    </button>
                                </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($browseData['rows'] as $idx => $row)
                            <tr class="border-b border-gray-100 hover:bg-gray-50 {{ $loop->even ? 'bg-gray-25' : '' }}">
                                <td class="py-2 px-3 text-xs text-gray-400">{{ ($browsePage - 1) * $browsePerPage + $idx + 1 }}</td>
                                @foreach($browseData['columns'] as $col)
                                <td class="py-2 px-3 font-mono text-xs text-gray-700 max-w-[200px] truncate" title="{{ $row->$col }}">
                                    @if(is_null($row->$col))
                                        <span class="text-gray-300 italic">NULL</span>
                                    @elseif($row->$col === '')
                                        <span class="text-gray-300 italic">empty</span>
                                    @else
                                        {{ $row->$col }}
                                    @endif
                                </td>
                                @endforeach
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                <div class="flex items-center justify-between mt-4 pt-4 border-t border-gray-200">
                    <button
                        wire:click="browsePrevPage()"
                        {{ $browsePage <= 1 ? 'disabled' : '' }}
                        class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-md border
                            {{ $browsePage <= 1
                                ? 'text-gray-300 bg-gray-50 border-gray-200 cursor-not-allowed'
                                : 'text-gray-700 bg-white hover:bg-gray-50 border-gray-300' }}"
                    >
                        <x-heroicon-o-chevron-left class="w-4 h-4 mr-1" /> Previous
                    </button>
                    <span class="text-sm text-gray-600">
                        Page {{ $browsePage }} of {{ $this->getBrowseTotalPages() }}
                    </span>
                    <button
                        wire:click="browseNextPage()"
                        {{ $browsePage >= $this->getBrowseTotalPages() ? 'disabled' : '' }}
                        class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-md border
                            {{ $browsePage >= $this->getBrowseTotalPages()
                                ? 'text-gray-300 bg-gray-50 border-gray-200 cursor-not-allowed'
                                : 'text-gray-700 bg-white hover:bg-gray-50 border-gray-300' }}"
                    >
                        Next <x-heroicon-o-chevron-right class="w-4 h-4 ml-1" />
                    </button>
                </div>
                @endif
            @endif
        </div>
        @endif

        {{-- STRUCTURE TAB --}}
        @if($activeTab === 'structure')
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900">
                    @if($structureTable)
                        Structure: <span class="font-mono text-primary-600">{{ $structureTable }}</span>
                    @else
                        Select a Table to View Structure
                    @endif
                </h2>
            </div>

            @if(! $structureTable)
            <div class="text-center py-12 text-gray-500">
                <x-heroicon-o-cog-6-tooth class="w-12 h-12 mx-auto mb-3 text-gray-300" />
                <p>Select a table from the Overview tab to view its structure.</p>
                <button
                    wire:click="switchTab('overview')"
                    class="mt-3 inline-flex items-center px-4 py-2 text-sm font-medium text-primary-600 hover:text-primary-700"
                >
                    ← Go to Overview
                </button>
            </div>
            @else
                {{-- Columns --}}
                <h3 class="text-sm font-semibold text-gray-700 mb-2">Columns</h3>
                <div class="overflow-x-auto mb-6">
                    <table class="w-full text-sm border border-gray-200 rounded-lg">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200">
                                <th class="py-2 px-3 text-left text-xs font-semibold text-gray-500 uppercase">Column</th>
                                <th class="py-2 px-3 text-left text-xs font-semibold text-gray-500 uppercase">Type</th>
                                <th class="py-2 px-3 text-center text-xs font-semibold text-gray-500 uppercase">Nullable</th>
                                <th class="py-2 px-3 text-left text-xs font-semibold text-gray-500 uppercase">Default</th>
                                <th class="py-2 px-3 text-left text-xs font-semibold text-gray-500 uppercase">Key</th>
                                <th class="py-2 px-3 text-left text-xs font-semibold text-gray-500 uppercase">Extra</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($tableStructure['columns'] ?? [] as $col)
                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                <td class="py-2 px-3 font-mono text-xs font-medium text-gray-900">{{ $col->COLUMN_NAME }}</td>
                                <td class="py-2 px-3 text-xs text-gray-600">
                                    {{ $col->COLUMN_TYPE }}
                                    @if($col->CHARACTER_MAXIMUM_LENGTH)
                                        <span class="text-gray-400">({{ $col->CHARACTER_MAXIMUM_LENGTH }})</span>
                                    @endif
                                </td>
                                <td class="py-2 px-3 text-center text-xs">
                                    @if($col->IS_NULLABLE === 'YES')
                                        <span class="text-green-600">YES</span>
                                    @else
                                        <span class="text-gray-400">NO</span>
                                    @endif
                                </td>
                                <td class="py-2 px-3 text-xs text-gray-500">
                                    @if(is_null($col->COLUMN_DEFAULT))
                                        <span class="text-gray-300 italic">NULL</span>
                                    @else
                                        {{ $col->COLUMN_DEFAULT }}
                                    @endif
                                </td>
                                <td class="py-2 px-3 text-xs">
                                    @if($col->COLUMN_KEY === 'PRI')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">PRIMARY</span>
                                    @elseif($col->COLUMN_KEY === 'UNI')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">UNIQUE</span>
                                    @elseif($col->COLUMN_KEY === 'MUL')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800">INDEX</span>
                                    @endif
                                </td>
                                <td class="py-2 px-3 text-xs text-gray-500">{{ $col->EXTRA }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Indexes --}}
                @if(! empty($tableStructure['indexes']))
                <h3 class="text-sm font-semibold text-gray-700 mb-2">Indexes</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm border border-gray-200 rounded-lg">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200">
                                <th class="py-2 px-3 text-left text-xs font-semibold text-gray-500 uppercase">Index Name</th>
                                <th class="py-2 px-3 text-left text-xs font-semibold text-gray-500 uppercase">Columns</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($tableStructure['indexes'] as $idx)
                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                <td class="py-2 px-3 font-mono text-xs font-medium text-gray-900">{{ $idx->INDEX_NAME }}</td>
                                <td class="py-2 px-3 text-xs text-gray-600">{{ $idx->columns_list }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            @endif
        </div>
        @endif

        {{-- SQL QUERY TAB --}}
        @if($activeTab === 'sql')
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6" x-data>
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900">SQL Query Editor</h2>
                <span class="text-xs text-gray-400">Only SELECT, SHOW, DESCRIBE, EXPLAIN, INSERT, UPDATE, DELETE are allowed</span>
            </div>

            <div class="mb-4">
                <textarea
                    wire:model="sqlQuery"
                    rows="6"
                    placeholder="Enter your SQL query here...&#10;&#10;Examples:&#10;SELECT * FROM customers LIMIT 10;&#10;SHOW TABLES;&#10;DESCRIBE users;&#10;SELECT COUNT(*) as total FROM sales_records;"
                    class="w-full font-mono text-sm rounded-lg border border-gray-300 bg-gray-50 p-4
                        focus:border-primary-500 focus:ring-primary-500 focus:ring-1 focus:outline-none
                        dark:bg-gray-900 dark:border-gray-600 dark:text-gray-300"
                    x-on:keydown.ctrl.enter="$wire.executeSql()"
                    x-on:keydown.meta.enter="$wire.executeSql()"
                ></textarea>
                <p class="mt-1 text-xs text-gray-400">Press Ctrl+Enter to execute</p>
            </div>

            <div class="flex items-center gap-2 mb-6">
                <button
                    wire:click="executeSql()"
                    class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg text-white bg-primary-600 hover:bg-primary-700 focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
                >
                    <x-heroicon-o-play class="w-4 h-4 mr-2" />
                    Execute Query
                </button>
                <button
                    wire:click="clearSqlQuery"
                    class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg text-gray-700 bg-white border border-gray-300 hover:bg-gray-50"
                >
                    Clear
                </button>
                @if($sqlExecutionTime)
                    <span class="text-xs text-gray-500 ml-2">Execution time: {{ $sqlExecutionTime }}</span>
                @endif
            </div>

            {{-- Error --}}
            @if($sqlError)
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                <div class="flex items-start">
                    <x-heroicon-o-exclamation-circle class="w-5 h-5 text-red-500 mt-0.5 mr-3 flex-shrink-0" />
                    <div>
                        <h3 class="text-sm font-medium text-red-800">Query Error</h3>
                        <p class="mt-1 text-sm text-red-700 font-mono">{{ $sqlError }}</p>
                    </div>
                </div>
            </div>
            @endif

            {{-- Affected Rows (for non-SELECT) --}}
            @if($sqlAffectedRows > 0 && empty($sqlError))
            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
                <div class="flex items-center">
                    <x-heroicon-o-check-circle class="w-5 h-5 text-green-500 mr-3" />
                    <span class="text-sm text-green-700">{{ number_format($sqlAffectedRows) }} row(s) affected.</span>
                </div>
            </div>
            @endif

            {{-- Results --}}
            @if(! empty($sqlResults))
            <div class="overflow-x-auto">
                <table class="w-full text-sm border border-gray-200 rounded-lg">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200">
                            @foreach($sqlColumns as $col)
                            <th class="py-2 px-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ $col }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($sqlResults as $row)
                        <tr class="border-b border-gray-100 hover:bg-gray-50">
                            @foreach($sqlColumns as $col)
                            <td class="py-2 px-3 font-mono text-xs text-gray-700 max-w-[300px] truncate" title="{{ $row[$col] ?? '' }}">
                                @if(is_null($row[$col]))
                                    <span class="text-gray-300 italic">NULL</span>
                                @else
                                    {{ $row[$col] }}
                                @endif
                            </td>
                            @endforeach
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-2 text-xs text-gray-500">{{ count($sqlResults) }} row(s) returned</p>
            @endif
        </div>
        @endif
    </div>
</x-filament-panels::page>
