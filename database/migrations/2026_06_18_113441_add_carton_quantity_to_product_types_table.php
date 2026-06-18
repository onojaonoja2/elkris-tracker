<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $cartonMap = [
            'Elkris Oat Flour' => [
                650 => 24,
                1300 => 12,
                5000 => 3,
            ],
            'Elkris Plantain Flour' => [
                900 => 20,
                1800 => 10,
            ],
            'Elkris Poundo Yam' => [
                1800 => 12,
            ],
        ];

        $productTypes = DB::table('product_types')->get();

        foreach ($productTypes as $productType) {
            $grammages = is_string($productType->available_grammages)
                ? json_decode($productType->available_grammages, true)
                : $productType->available_grammages;
            $cartons = $cartonMap[$productType->name] ?? [];
            $updated = [];

            foreach ($grammages as $grammage) {
                if (is_array($grammage)) {
                    $weight = $grammage['grammage'] ?? $grammage['weight'] ?? null;
                    if ($weight && isset($cartons[$weight])) {
                        $updated[] = ['grammage' => (int) $weight, 'carton_quantity' => $cartons[$weight]];
                    } else {
                        $updated[] = array_merge($grammage, ['carton_quantity' => $grammage['carton_quantity'] ?? 1]);
                    }
                } else {
                    $weight = (int) $grammage;
                    $updated[] = ['grammage' => $weight, 'carton_quantity' => $cartons[$weight] ?? 1];
                }
            }

            DB::table('product_types')
                ->where('id', $productType->id)
                ->update(['available_grammages' => $updated]);
        }
    }

    public function down(): void
    {
        $productTypes = DB::table('product_types')->get();

        foreach ($productTypes as $productType) {
            $grammages = is_string($productType->available_grammages)
                ? json_decode($productType->available_grammages, true)
                : $productType->available_grammages;
            $updated = [];

            foreach ($grammages as $grammage) {
                if (is_array($grammage)) {
                    $updated[] = $grammage['grammage'] ?? $grammage;
                } else {
                    $updated[] = $grammage;
                }
            }

            DB::table('product_types')
                ->where('id', $productType->id)
                ->update(['available_grammages' => $updated]);
        }
    }
};
