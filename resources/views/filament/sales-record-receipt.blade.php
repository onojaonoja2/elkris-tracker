<div class="p-4">
    @if($record->receipt_path)
        <img
            src="{{ \Illuminate\Support\Facades\Storage::disk('s3')->url($record->receipt_path) }}"
            alt="Sales Receipt"
            style="max-width: 100%; max-height: 80vh; object-fit: contain;"
            class="rounded-lg shadow-lg"
        />
        <p class="text-sm text-gray-500 mt-2">
            Original file: {{ $record->receipt_original_name ?? 'Receipt' }}
        </p>
    @else
        <p class="text-gray-500">No receipt uploaded.</p>
    @endif
</div>
