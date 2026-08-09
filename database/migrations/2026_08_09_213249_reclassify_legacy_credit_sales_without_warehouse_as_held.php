<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('sales_records')
            ->whereIn('agent_type', ['open_market', 'retail_market'])
            ->where('stock_source', 'warehouse')
            ->whereNull('warehouse_id')
            ->update(['stock_source' => 'held']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('sales_records')
            ->whereIn('agent_type', ['open_market', 'retail_market'])
            ->where('stock_source', 'held')
            ->whereNull('warehouse_id')
            ->update(['stock_source' => 'warehouse']);
    }
};
