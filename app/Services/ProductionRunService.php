<?php

namespace App\Services;

use App\Models\ProductionRun;
use App\Models\RawMaterial;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductionRunService
{
    /**
     * Create a production run and deduct the raw material quantity atomically.
     *
     * @param  array<string, mixed>  $data
     */
    public static function create(array $data): ProductionRun
    {
        return DB::transaction(function () use ($data) {
            $rawMaterial = RawMaterial::lockForUpdate()->findOrFail($data['raw_material_id']);
            $quantityUsed = (float) $data['quantity_used'];

            if ($rawMaterial->quantity < $quantityUsed) {
                throw ValidationException::withMessages([
                    'quantity_used' => "Only {$rawMaterial->quantity} {$rawMaterial->unit_of_measure} available.",
                ]);
            }

            $rawMaterial->decrement('quantity', $quantityUsed);

            return ProductionRun::create($data);
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
}
