<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('production_runs', function (Blueprint $table) {
            $table->foreignId('output_warehouse_id')->nullable()->after('product_type_id')->constrained('warehouses')->nullOnDelete();
        });

        $fallbackWarehouseId = DB::table('warehouses')->where('has_production_module', true)->where('is_active', true)->orderBy('id')->value('id');

        if ($fallbackWarehouseId) {
            DB::table('production_runs')->whereNull('output_warehouse_id')->update(['output_warehouse_id' => $fallbackWarehouseId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_runs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('output_warehouse_id');
        });
    }
};
