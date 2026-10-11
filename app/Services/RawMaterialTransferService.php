<?php

namespace App\Services;

use App\Models\RawMaterial;
use App\Models\RawMaterialStock;
use App\Models\RawMaterialTransfer;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RawMaterialTransferService
{
    /**
     * Request raw materials from one warehouse to another (typically a
     * raw-material warehouse to the production warehouse).
     */
    public static function requestTransfer(
        int $fromWarehouseId,
        int $toWarehouseId,
        int $rawMaterialId,
        float $quantity,
        int $requestedBy,
        ?string $notes = null
    ): RawMaterialTransfer {
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Quantity must be greater than zero.',
            ]);
        }

        if ($fromWarehouseId === $toWarehouseId) {
            throw ValidationException::withMessages([
                'to_warehouse_id' => 'Source and destination warehouses must differ.',
            ]);
        }

        $material = RawMaterial::find($rawMaterialId);

        if (! $material) {
            throw ValidationException::withMessages([
                'raw_material_id' => 'Unknown raw material.',
            ]);
        }

        self::assertSourceAvailable($material, $fromWarehouseId, $quantity);

        $transfer = RawMaterialTransfer::create([
            'from_warehouse_id' => $fromWarehouseId,
            'to_warehouse_id' => $toWarehouseId,
            'raw_material_id' => $rawMaterialId,
            'quantity' => $quantity,
            'status' => 'requested',
            'requested_by' => $requestedBy,
            'notes' => $notes,
        ]);

        $managerId = Warehouse::whereKey($toWarehouseId)->value('manager_id');

        if ($managerId) {
            NotificationService::notifyUser(
                $managerId,
                'raw_material_transfer_requested',
                'Raw material transfer requested',
                "Transfer #{$transfer->id} ({$material->name}, {$quantity} {$material->unit_of_measure}) is awaiting receipt at your warehouse.",
                $transfer->id,
                'raw_material_transfer'
            );
        }

        return $transfer;
    }

    /**
     * Dispatch a requested transfer: deduct the source warehouse balance.
     */
    public static function dispatchTransfer(RawMaterialTransfer $transfer, int $dispatcherId): void
    {
        DB::transaction(function () use ($transfer, $dispatcherId) {
            $transfer = RawMaterialTransfer::whereKey($transfer->getKey())->lockForUpdate()->firstOrFail();

            if ($transfer->status !== 'requested') {
                throw ValidationException::withMessages([
                    'status' => 'Only requested transfers can be dispatched.',
                ]);
            }

            $material = RawMaterial::whereKey($transfer->raw_material_id)->lockForUpdate()->firstOrFail();

            self::assertSourceAvailable($material, $transfer->from_warehouse_id, (float) $transfer->quantity);

            self::decrementSource($material, $transfer->from_warehouse_id, (float) $transfer->quantity);

            $transfer->update([
                'status' => 'dispatched',
                'dispatched_by' => $dispatcherId,
                'dispatched_at' => now(),
            ]);
        });
    }

    /**
     * Receive a dispatched transfer: credit the destination warehouse balance.
     */
    public static function receiveTransfer(RawMaterialTransfer $transfer, int $receiverId): void
    {
        DB::transaction(function () use ($transfer, $receiverId) {
            $transfer = RawMaterialTransfer::whereKey($transfer->getKey())->lockForUpdate()->firstOrFail();

            if ($transfer->status !== 'dispatched') {
                throw ValidationException::withMessages([
                    'status' => 'Only dispatched transfers can be received.',
                ]);
            }

            $material = RawMaterial::whereKey($transfer->raw_material_id)->lockForUpdate()->firstOrFail();
            $quantity = (float) $transfer->quantity;

            RawMaterialStock::where('warehouse_id', $transfer->to_warehouse_id)
                ->where('raw_material_id', $material->id)
                ->lockForUpdate()
                ->first()
                ?? RawMaterialStock::create([
                    'warehouse_id' => $transfer->to_warehouse_id,
                    'raw_material_id' => $material->id,
                    'quantity' => 0,
                ]);

            RawMaterialStock::where('warehouse_id', $transfer->to_warehouse_id)
                ->where('raw_material_id', $material->id)
                ->increment('quantity', $quantity);

            $material->increment('quantity', $quantity);

            $transfer->update([
                'status' => 'received',
                'received_by' => $receiverId,
                'received_at' => now(),
            ]);
        });
    }

    public static function cancelTransfer(RawMaterialTransfer $transfer): void
    {
        DB::transaction(function () use ($transfer) {
            $transfer = RawMaterialTransfer::whereKey($transfer->getKey())->lockForUpdate()->firstOrFail();

            if ($transfer->status !== 'requested') {
                throw ValidationException::withMessages([
                    'status' => 'Only requested transfers can be cancelled.',
                ]);
            }

            $transfer->update(['status' => 'cancelled']);
        });
    }

    private static function assertSourceAvailable(RawMaterial $material, int $warehouseId, float $quantity): void
    {
        $row = RawMaterialStock::where('warehouse_id', $warehouseId)
            ->where('raw_material_id', $material->id)
            ->first();

        $available = $row
            ? (float) $row->quantity
            : ($material->stocks()->exists() ? 0.0 : (float) $material->quantity);

        if ($available < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => "Insufficient {$material->name} at the source warehouse (requested: {$quantity}, available: {$available}).",
            ]);
        }
    }

    private static function decrementSource(RawMaterial $material, int $warehouseId, float $quantity): void
    {
        $row = RawMaterialStock::where('warehouse_id', $warehouseId)
            ->where('raw_material_id', $material->id)
            ->lockForUpdate()
            ->first();

        if ($row) {
            $row->decrement('quantity', $quantity);
        } else {
            $material->decrement('quantity', $quantity);

            return;
        }

        $material->decrement('quantity', $quantity);
    }
}
