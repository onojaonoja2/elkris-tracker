<?php

use App\Services\OrderProductBackfillService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Backfills `product_type_id` and canonical product names on historical
     * order items that were created without a product type reference, and
     * fixes agent stock rows recorded with misspelled product names.
     */
    public function up(): void
    {
        OrderProductBackfillService::backfillOrderProducts();
        OrderProductBackfillService::fixMisspelledAgentStockNames();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Data backfill; the original product names and null product type
        // references are not recoverable from the current schema.
    }
};
