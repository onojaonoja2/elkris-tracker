<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Pre-existing Orders</h2>
                    <p class="text-sm text-gray-500 mt-1">Control whether reps and team leads can mark orders as pre-existing (migrated) to bypass the approval workflow.</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" class="sr-only peer" wire:model="migratedOrdersEnabled" wire:change="save">
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-600"></div>
                </label>
            </div>
            @if($migratedOrdersEnabled)
                <div class="mt-4 p-3 bg-green-50 border border-green-200 rounded-lg">
                    <p class="text-sm text-green-700 font-medium">✓ Pre-existing orders are enabled. Reps and leads can use the toggle when creating orders.</p>
                </div>
            @else
                <div class="mt-4 p-3 bg-gray-50 border border-gray-200 rounded-lg">
                    <p class="text-sm text-gray-600">Pre-existing orders are disabled. The toggle will be hidden from reps and leads.</p>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
