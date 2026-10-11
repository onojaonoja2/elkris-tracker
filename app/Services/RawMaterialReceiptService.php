<?php

namespace App\Services;

use App\Models\RawMaterial;
use App\Models\RawMaterialReceipt;
use App\Models\RawMaterialStock;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RawMaterialReceiptService
{
    /**
     * Record raw materials received from procurement into a warehouse.
     * Stock lands only on approval.
     *
     * Sources: 'supplier' (procurement supplier name) or 'other'
     * (free-text source name plus optional dispatch papers).
     *
     * @param  array<string, mixed>  $data
     */
    public static function submit(array $data, int $requestedBy): RawMaterialReceipt
    {
        $warehouseId = (int) ($data['warehouse_id'] ?? 0);
        $sourceType = $data['source_type'] ?? 'supplier';

        if (! in_array($sourceType, ['supplier', 'other'], true)) {
            $sourceType = 'supplier';
        }

        $sourceName = trim((string) ($sourceType === 'supplier'
            ? ($data['supplier_name'] ?? '')
            : ($data['source_name'] ?? '')));

        $warehouse = Warehouse::find($warehouseId);

        if (! $warehouse) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'Unknown warehouse.',
            ]);
        }

        if (! $warehouse->has_raw_material_module) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'The selected warehouse does not hold raw materials.',
            ]);
        }

        if ($sourceName === '') {
            throw ValidationException::withMessages([
                'source_name' => $sourceType === 'supplier'
                    ? 'Supplier name is required.'
                    : 'Source name is required.',
            ]);
        }

        $papersPath = null;
        if ($sourceType === 'other' && ! empty($data['dispatch_papers'])) {
            $papers = $data['dispatch_papers'];
            $papersPath = is_array($papers) ? json_encode(array_values($papers)) : $papers;
        }

        $lines = collect($data['items'] ?? [])
            ->filter(fn ($item): bool => is_array($item) && isset($item['raw_material_id']) && (float) ($item['quantity'] ?? 0) > 0)
            ->map(fn (array $item): array => [
                'raw_material_id' => (int) $item['raw_material_id'],
                'quantity' => (float) $item['quantity'],
            ])
            ->values();

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Add at least one raw material to this receipt.',
            ]);
        }

        foreach ($lines as $line) {
            if (! RawMaterial::whereKey($line['raw_material_id'])->exists()) {
                throw ValidationException::withMessages([
                    'items' => 'Unknown raw material selected.',
                ]);
            }
        }

        $receipt = RawMaterialReceipt::create([
            'warehouse_id' => $warehouseId,
            'source_type' => $sourceType,
            'supplier_name' => $sourceName,
            'items' => $lines->all(),
            'dispatch_papers_path' => $papersPath,
            'status' => 'pending',
            'requested_by' => $requestedBy,
            'notes' => $data['notes'] ?? null,
        ]);

        NotificationService::notifyRoles(
            ['accountant', 'general_accountant', 'manager', 'admin', 'general_manager'],
            'raw_material_receipt_submitted',
            'Raw material receipt submitted',
            "Receipt #{$receipt->id} from {$sourceName} into {$warehouse->name} is awaiting approval.",
            $receipt->id,
            'raw_material_receipt'
        );

        return $receipt;
    }

    /**
     * Approve a pending receipt: credit the warehouse balances.
     */
    public static function approve(RawMaterialReceipt $receipt, int $approverId): void
    {
        DB::transaction(function () use ($receipt, $approverId) {
            $receipt = RawMaterialReceipt::whereKey($receipt->getKey())->lockForUpdate()->firstOrFail();

            if ($receipt->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => 'Only pending receipts can be approved.',
                ]);
            }

            foreach ($receipt->lines() as $line) {
                $material = RawMaterial::whereKey($line['raw_material_id'])->lockForUpdate()->firstOrFail();

                RawMaterialStock::where('warehouse_id', $receipt->warehouse_id)
                    ->where('raw_material_id', $material->id)
                    ->lockForUpdate()
                    ->first()
                    ?? RawMaterialStock::create([
                        'warehouse_id' => $receipt->warehouse_id,
                        'raw_material_id' => $material->id,
                        'quantity' => 0,
                    ]);

                RawMaterialStock::where('warehouse_id', $receipt->warehouse_id)
                    ->where('raw_material_id', $material->id)
                    ->increment('quantity', $line['quantity']);

                $material->increment('quantity', $line['quantity']);
            }

            $receipt->update([
                'status' => 'approved',
                'approved_by' => $approverId,
                'approved_at' => now(),
            ]);
        });
    }

    /**
     * Reject a pending receipt. No stock moves.
     */
    public static function reject(RawMaterialReceipt $receipt, string $reason, int $approverId): void
    {
        DB::transaction(function () use ($receipt, $reason, $approverId) {
            $receipt = RawMaterialReceipt::whereKey($receipt->getKey())->lockForUpdate()->firstOrFail();

            if ($receipt->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => 'Only pending receipts can be rejected.',
                ]);
            }

            $receipt->update([
                'status' => 'rejected',
                'rejection_reason' => $reason,
                'approved_by' => $approverId,
                'approved_at' => now(),
            ]);
        });
    }
}
