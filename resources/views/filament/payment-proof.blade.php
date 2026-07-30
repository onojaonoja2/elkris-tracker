<div class="p-4">
    @if($record->payment_proof_path)
        <img
            src="{{ \Illuminate\Support\Facades\Storage::disk('s3')->url($record->payment_proof_path) }}"
            alt="Payment Proof"
            style="max-width: 100%; max-height: 80vh; object-fit: contain;"
            class="rounded-lg shadow-lg"
        />
        <p class="text-sm text-gray-500 mt-2">
            Uploaded by: {{ $record->paymentProofUploader?->name ?? 'Unknown' }}
            @if($record->payment_proof_uploaded_at)
                on {{ $record->payment_proof_uploaded_at->format('M d, Y H:i') }}
            @endif
        </p>
    @else
        <p class="text-gray-500">No payment proof uploaded.</p>
    @endif
</div>
