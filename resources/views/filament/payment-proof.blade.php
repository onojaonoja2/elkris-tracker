<div class="p-4">
    @if($record->payment_proof_path)
        <img
            src="{{ \Illuminate\Support\Facades\Storage::disk('s3')->url($record->payment_proof_path) }}"
            alt="Payment Proof"
            style="max-width: 100%; max-height: 80vh; object-fit: contain;"
            class="rounded-lg shadow-lg"
        />
        <div class="mt-4 flex items-center justify-between">
            <p class="text-sm text-gray-500">
                Uploaded by: {{ $record->paymentProofUploader?->name ?? 'Unknown' }}
                @if($record->payment_proof_uploaded_at)
                    on {{ $record->payment_proof_uploaded_at->format('M d, Y H:i') }}
                @endif
            </p>
            <a
                href="{{ \Illuminate\Support\Facades\Storage::disk('s3')->temporaryUrl($record->payment_proof_path, now()->addMinutes(15)) }}"
                download="{{ basename($record->payment_proof_path) }}"
                class="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-500"
            >
                Download
            </a>
        </div>
    @else
        <p class="text-gray-500">No payment proof uploaded.</p>
    @endif
</div>
