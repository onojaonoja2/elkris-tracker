<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\ProductionRun;
use App\Models\RawMaterial;
use App\Models\RawMaterialStock;
use App\Models\StockTransaction;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductionRunService
{
    /**
     * Create a production run and deduct all raw material quantities atomically.
     *
     * Materials are consumed from the production warehouse balance when one is
     * designated and the material is tracked per warehouse; otherwise the
     * legacy global pool is used.
     *
     * @param  array<string, mixed>  $data
     */
    public static function create(array $data): ProductionRun
    {
        return DB::transaction(function () use ($data) {
            $materials = $data['raw_materials'] ?? [];

            if (empty($materials)) {
                throw ValidationException::withMessages([
                    'raw_materials' => 'At least one raw material is required.',
                ]);
            }

            $deductions = self::planDeductions($materials, $data['output_warehouse_id'] ?? null);

            self::applyDeductions($deductions);

            $data['status'] = $data['status'] ?? 'pending_review';

            $run = ProductionRun::create($data);

            $syncData = [];
            foreach ($materials as $material) {
                $syncData[$material['raw_material_id']] = ['quantity_used' => $material['quantity_used']];
            }
            $run->rawMaterials()->sync($syncData);

            return $run;
        });
    }

    /**
     * Update a production run, restoring old material quantities and applying new ones.
     *
     * @param  array<string, mixed>  $data
     */
    public static function update(ProductionRun $run, array $data): ProductionRun
    {
        if ($run->isLocked()) {
            throw ValidationException::withMessages([
                'status' => 'This production run has already been reviewed and cannot be edited.',
            ]);
        }

        return DB::transaction(function () use ($run, $data) {
            $materials = $data['raw_materials'] ?? [];

            if (empty($materials)) {
                throw ValidationException::withMessages([
                    'raw_materials' => 'At least one raw material is required.',
                ]);
            }

            // Restore previous quantities.
            self::restoreRunMaterials($run);

            $deductions = self::planDeductions($materials, $data['output_warehouse_id'] ?? $run->output_warehouse_id);

            self::applyDeductions($deductions);

            $run->update($data);

            $syncData = [];
            foreach ($materials as $material) {
                $syncData[$material['raw_material_id']] = ['quantity_used' => $material['quantity_used']];
            }
            $run->rawMaterials()->sync($syncData);

            return $run;
        });
    }

    /**
     * Review a production run and lock it from further edits.
     *
     * The review decision is always recorded. Finished goods are posted to the
     * run's output warehouse when it maps to a stockable product and an output
     * warehouse is set; otherwise the reviewer is notified.
     *
     * @param  array<string, mixed>  $data
     */
    public static function review(ProductionRun $run, array $data, int $reviewerId): ProductionRun
    {
        return DB::transaction(function () use ($run, $data, $reviewerId) {
            if ($run->isLocked()) {
                throw ValidationException::withMessages([
                    'status' => 'This production run has already been reviewed.',
                ]);
            }

            $run->update([
                'status' => $data['status'],
                'accountant_notes' => $data['accountant_notes'] ?? null,
                'accountant_reviewed_by' => $reviewerId,
                'accountant_reviewed_at' => now(),
            ]);

            if ($run->isReviewed()) {
                $freshRun = $run->fresh();

                if ($freshRun->mapsToStockableProduct() && ! self::resolveOutputWarehouse($freshRun)) {
                    NotificationService::notifyUser(
                        $reviewerId,
                        'production_goods_not_posted',
                        'Production review saved',
                        "Run #{$run->id} was reviewed, but finished goods were not posted to inventory because no output warehouse is set.",
                        $run->id,
                        'production_run'
                    );
                } else {
                    self::postFinishedGoods($freshRun, $reviewerId);
                }
            }

            return $run;
        });
    }

    /**
     * Credit the run's output warehouse with its finished goods.
     * Only reviewed runs carrying a product mapping post stock, exactly once.
     */
    public static function postFinishedGoods(ProductionRun $run, int $reviewerId): void
    {
        if (! $run->mapsToStockableProduct()) {
            return;
        }

        $store = self::resolveOutputWarehouse($run);

        if (! $store) {
            throw ValidationException::withMessages([
                'status' => 'No output warehouse is set for this production run.',
            ]);
        }

        $inventory = Inventory::where([
            'warehouse_id' => $store->id,
            'product_type_id' => $run->product_type_id,
            'grammage' => $run->grammage,
        ])->lockForUpdate()->first();

        if (! $inventory) {
            $inventory = Inventory::create([
                'warehouse_id' => $store->id,
                'product_type_id' => $run->product_type_id,
                'grammage' => $run->grammage,
                'quantity' => 0,
            ]);
        }

        $inventory->increment('quantity', (int) $run->finished_quantity);

        StockTransaction::create([
            'type' => 'received',
            'transaction_date' => now()->toDateString(),
            'product_type_id' => $run->product_type_id,
            'product_name' => $run->productType?->name ?? $run->output_name,
            'grammage' => $run->grammage,
            'quantity' => (int) $run->finished_quantity,
            'disbursed_to' => 'Production Run #'.$run->id,
            'user_id' => $reviewerId,
            'warehouse_id' => $store->id,
        ]);
    }

    public static function restoreMaterials(ProductionRun $run): void
    {
        self::restoreRunMaterials($run);
    }

    /**
     * Resolve the warehouse a run produces into and consumes from: the run's
     * own output warehouse, falling back to the legacy designated store.
     */
    private static function resolveOutputWarehouse(ProductionRun $run): ?Warehouse
    {
        return $run->outputWarehouse ?? Warehouse::productionStore();
    }

    /**
     * Validate availability and plan deductions for the given material lines.
     *
     * @param  array<int, array<string, mixed>>  $materials
     * @return array<int, array{material: RawMaterial, quantity: float, warehouseId: ?int}>
     */
    private static function planDeductions(array $materials, ?int $outputWarehouseId = null): array
    {
        $warehouseId = $outputWarehouseId ?? Warehouse::productionStore()?->id;
        $deductions = [];

        foreach ($materials as $index => $material) {
            $rawMaterial = RawMaterial::lockForUpdate()->findOrFail($material['raw_material_id']);
            $quantityUsed = (float) $material['quantity_used'];

            $scopedWarehouseId = $warehouseId && $rawMaterial->stocks()->exists() ? $warehouseId : null;

            $available = $scopedWarehouseId
                ? (float) (RawMaterialStock::where('warehouse_id', $scopedWarehouseId)->where('raw_material_id', $rawMaterial->id)->value('quantity') ?? 0)
                : (float) $rawMaterial->quantity;

            if ($available < $quantityUsed) {
                $location = $scopedWarehouseId ? ' in '.(Warehouse::whereKey($scopedWarehouseId)->value('name') ?? 'the output warehouse') : '';

                throw ValidationException::withMessages([
                    "raw_materials.{$index}.quantity_used" => "Only {$available} {$rawMaterial->unit_of_measure} of {$rawMaterial->name} available{$location}.",
                ]);
            }

            $deductions[] = ['material' => $rawMaterial, 'quantity' => $quantityUsed, 'warehouseId' => $scopedWarehouseId];
        }

        return $deductions;
    }

    /**
     * @param  array<int, array{material: RawMaterial, quantity: float, warehouseId: ?int}>  $deductions
     */
    private static function applyDeductions(array $deductions): void
    {
        foreach ($deductions as $deduction) {
            if ($deduction['warehouseId']) {
                RawMaterialStock::where('warehouse_id', $deduction['warehouseId'])
                    ->where('raw_material_id', $deduction['material']->id)
                    ->lockForUpdate()
                    ->first()
                    ?->decrement('quantity', $deduction['quantity']);
            }

            $deduction['material']->decrement('quantity', $deduction['quantity']);
        }
    }

    private static function restoreRunMaterials(ProductionRun $run): void
    {
        $warehouseId = $run->output_warehouse_id ?? Warehouse::productionStore()?->id;

        foreach ($run->rawMaterials as $existingMaterial) {
            $quantity = (float) $existingMaterial->pivot->quantity_used;

            if ($warehouseId && $existingMaterial->stocks()->exists()) {
                RawMaterialStock::firstOrCreate(
                    ['warehouse_id' => $warehouseId, 'raw_material_id' => $existingMaterial->id],
                    ['quantity' => 0]
                )->increment('quantity', $quantity);
            }

            $existingMaterial->increment('quantity', $quantity);
        }
    }
}
