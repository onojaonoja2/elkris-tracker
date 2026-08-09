<?php

namespace App\Services;

use App\Models\ProductionRun;
use App\Models\RawMaterial;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductionRunService
{
    /**
     * Create a production run and deduct all raw material quantities atomically.
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

            $deductions = [];
            foreach ($materials as $index => $material) {
                $rawMaterial = RawMaterial::lockForUpdate()->findOrFail($material['raw_material_id']);
                $quantityUsed = (float) $material['quantity_used'];

                if ($rawMaterial->quantity < $quantityUsed) {
                    throw ValidationException::withMessages([
                        "raw_materials.{$index}.quantity_used" => "Only {$rawMaterial->quantity} {$rawMaterial->unit_of_measure} of {$rawMaterial->name} available.",
                    ]);
                }

                $deductions[] = ['material' => $rawMaterial, 'quantity' => $quantityUsed];
            }

            foreach ($deductions as $deduction) {
                $deduction['material']->decrement('quantity', $deduction['quantity']);
            }

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
            foreach ($run->rawMaterials as $existingMaterial) {
                $existingMaterial->increment('quantity', (float) $existingMaterial->pivot->quantity_used);
            }

            $deductions = [];
            foreach ($materials as $index => $material) {
                $rawMaterial = RawMaterial::lockForUpdate()->findOrFail($material['raw_material_id']);
                $quantityUsed = (float) $material['quantity_used'];

                if ($rawMaterial->quantity < $quantityUsed) {
                    throw ValidationException::withMessages([
                        "raw_materials.{$index}.quantity_used" => "Only {$rawMaterial->quantity} {$rawMaterial->unit_of_measure} of {$rawMaterial->name} available.",
                    ]);
                }

                $deductions[] = ['material' => $rawMaterial, 'quantity' => $quantityUsed];
            }

            foreach ($deductions as $deduction) {
                $deduction['material']->decrement('quantity', $deduction['quantity']);
            }

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
     * @param  array<string, mixed>  $data
     */
    public static function review(ProductionRun $run, array $data, int $reviewerId): ProductionRun
    {
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

        return $run;
    }

    public static function restoreMaterials(ProductionRun $run): void
    {
        foreach ($run->rawMaterials as $material) {
            $material->increment('quantity', (float) $material->pivot->quantity_used);
        }
    }
}
