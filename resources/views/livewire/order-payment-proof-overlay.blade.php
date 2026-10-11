@if($this->proofOrder)
    <div
        class="absolute inset-0 z-10 flex items-center justify-center bg-gray-950/60 p-4"
        wire:click="closeProof"
        x-on:keydown.escape.window="$wire.closeProof()"
    >
        <div
            class="w-full max-w-2xl max-h-full overflow-auto rounded-xl bg-white shadow-xl dark:bg-gray-900"
            wire:click.stop
        >
            <div class="flex items-center justify-between gap-3 border-b px-4 py-3 dark:border-gray-700">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                    Payment Proof — Order #{{ $this->proofOrder->id }}
                </h3>
                <button
                    type="button"
                    wire:click="closeProof"
                    class="inline-flex items-center rounded-lg px-2 py-1 text-sm font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                    <span class="sr-only">Close</span>
                </button>
            </div>
            @include('filament.payment-proof', ['record' => $this->proofOrder])
        </div>
    </div>
@endif
