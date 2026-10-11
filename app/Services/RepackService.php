<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\ProductType;
use App\Models\Repack;
use App\Models\StockTransaction;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RepackService
{
    /**
     * Repackage stock from one SKU (e.g. bulk bags) into another (e.g. 650g
     * packs) within the same warehouse.
     *
     * Input and output quantities are entered manually; only input
     * availability is validated. Both legs are written atomically with a full
     * audit trail.
     *
     * @param  array<string, mixed>  $data
     */
    public static function post(array $data, int $userId): Repack
    {
        $warehouseId = (int) ($data['warehouse_id'] ?? 0);
        $inputProductTypeId = (int) ($data['input_product_type_id'] ?? 0);
        $inputGrammage = (int) ($data['input_grammage'] ?? 0);
        $inputQuantity = (int) ($data['input_quantity'] ?? 0);
        $outputProductTypeId = (int) ($data['output_product_type_id'] ?? 0);
        $outputGrammage = (int) ($data['output_grammage'] ?? 0);
        $outputQuantity = (int) ($data['output_quantity'] ?? 0);

        if (! Warehouse::whereKey($warehouseId)->exists()) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'Unknown warehouse.',
            ]);
        }

        if ($inputQuantity <= 0 || $outputQuantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Input and output quantities must be greater than zero.',
            ]);
        }

        if ($inputProductTypeId === $outputProductTypeId && $inputGrammage === $outputGrammage) {
            throw ValidationException::withMessages([
                'output_grammage' => 'Input and output SKUs must differ.',
            ]);
        }

        $inputProduct = ProductType::find($inputProductTypeId);
        $outputProduct = ProductType::find($outputProductTypeId);

        if (! $inputProduct || ! $outputProduct) {
            throw ValidationException::withMessages([
                'input_product_type_id' => 'Unknown product.',
            ]);
        }

        return DB::transaction(function () use ($data, $userId, $warehouseId, $inputProduct, $outputProduct, $inputProductTypeId, $inputGrammage, $inputQuantity, $outputProductTypeId, $outputGrammage, $outputQuantity) {
            $source = Inventory::where([
                'warehouse_id' => $warehouseId,
                'product_type_id' => $inputProductTypeId,
                'grammage' => $inputGrammage,
            ])->lockForUpdate()->first();

            $available = $source?->quantity ?? 0;

            if ($available < $inputQuantity) {
                throw ValidationException::withMessages([
                    'input_quantity' => "Insufficient {$inputProduct->name} ".ProductType::formatGrammage($inputProduct, $inputGrammage)." (requested: {$inputQuantity}, available: {$available}).",
                ]);
            }

            $source->decrement('quantity', $inputQuantity);

            $destination = Inventory::where([
                'warehouse_id' => $warehouseId,
                'product_type_id' => $outputProductTypeId,
                'grammage' => $outputGrammage,
            ])->lockForUpdate()->first();

            if (! $destination) {
                $destination = Inventory::create([
                    'warehouse_id' => $warehouseId,
                    'product_type_id' => $outputProductTypeId,
                    'grammage' => $outputGrammage,
                    'quantity' => 0,
                ]);
            }

            $destination->increment('quantity', $outputQuantity);

            $repack = Repack::create([
                'warehouse_id' => $warehouseId,
                'user_id' => $userId,
                'input_product_type_id' => $inputProductTypeId,
                'input_grammage' => $inputGrammage,
                'input_quantity' => $inputQuantity,
                'output_product_type_id' => $outputProductTypeId,
                'output_grammage' => $outputGrammage,
                'output_quantity' => $outputQuantity,
                'status' => 'posted',
                'notes' => $data['notes'] ?? null,
            ]);

            StockTransaction::create([
                'type' => 'disbursed',
                'transaction_date' => now()->toDateString(),
                'product_type_id' => $inputProductTypeId,
                'product_name' => $inputProduct->name,
                'grammage' => $inputGrammage,
                'quantity' => $inputQuantity,
                'disbursed_to' => "Repack #{$repack->id} (repacked into {$outputProduct->name} ".ProductType::formatGrammage($outputProduct, $outputGrammage).')',
                'user_id' => $userId,
                'warehouse_id' => $warehouseId,
            ]);

            StockTransaction::create([
                'type' => 'received',
                'transaction_date' => now()->toDateString(),
                'product_type_id' => $outputProductTypeId,
                'product_name' => $outputProduct->name,
                'grammage' => $outputGrammage,
                'quantity' => $outputQuantity,
                'disbursed_to' => "Repack #{$repack->id} (repacked from {$inputProduct->name} ".ProductType::formatGrammage($inputProduct, $inputGrammage).')',
                'user_id' => $userId,
                'warehouse_id' => $warehouseId,
            ]);

            return $repack;
        });
    }
}
