<?php

namespace App\Services;

use App\Models\AgentStock;
use App\Models\Product;
use App\Models\ProductType;

class OrderProductBackfillService
{
    /**
     * Maps legacy product names stored on historical order items to the
     * canonical ProductType names they represent.
     *
     * @var array<string, string>
     */
    protected const ALIASES = [
        'Elkris Plantain' => 'Elkris Plantain Flour',
    ];

    /**
     * Backfills `product_type_id` and the canonical product name on order
     * items that were created without a product type reference.
     *
     * @return int Number of order items updated.
     */
    public static function backfillOrderProducts(): int
    {
        $updated = 0;

        Product::query()
            ->whereNull('product_type_id')
            ->chunkById(500, function ($products) use (&$updated) {
                foreach ($products as $product) {
                    $productType = static::resolveProductType($product->product_name);

                    if (! $productType) {
                        continue;
                    }

                    $product->update([
                        'product_type_id' => $productType->id,
                        'product_name' => $productType->name,
                    ]);

                    $updated++;
                }
            });

        return $updated;
    }

    /**
     * Fixes agent stock rows that were recorded with a misspelled product
     * name so they can be matched against orders and deliveries. When a
     * correctly-named stock row already exists for the same user and
     * grammage, the misspelled row's quantity is merged into it.
     *
     * @return int Number of stock rows fixed.
     */
    public static function fixMisspelledAgentStockNames(): int
    {
        $fixed = 0;

        AgentStock::query()
            ->where('product_name', 'Elkris Oat Flourx')
            ->chunkById(500, function ($rows) use (&$fixed) {
                foreach ($rows as $row) {
                    $existing = AgentStock::where('user_id', $row->user_id)
                        ->where('product_name', 'Elkris Oat Flour')
                        ->where('grammage', $row->grammage)
                        ->first();

                    if ($existing) {
                        $existing->increment('quantity', $row->quantity);
                        $row->delete();
                    } else {
                        $row->update(['product_name' => 'Elkris Oat Flour']);
                    }

                    $fixed++;
                }
            });

        return $fixed;
    }

    protected static function resolveProductType(string $productName): ?ProductType
    {
        return ProductType::where('name', $productName)->first()
            ?? ProductType::where('name', static::ALIASES[$productName] ?? '')->first();
    }
}
