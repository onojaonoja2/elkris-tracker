<x-filament-panels::page>
    {{ $this->form }}

    @if ($fileParsed)
        <div class="mt-6 rounded-lg bg-gray-50 p-4 dark:bg-gray-900">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">File Summary</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ $fileName }} &mdash; {{ count($fileHeaders) }} columns, {{ $totalRows }} rows
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach ($fileHeaders as $header)
                        <span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                            {{ $header }}
                        </span>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
