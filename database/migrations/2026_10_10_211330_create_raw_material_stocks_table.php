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
        Schema::create('raw_material_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('raw_material_id')->constrained('raw_materials')->cascadeOnDelete();
            $table->decimal('quantity', 20, 4)->default(0);
            $table->timestamps();

            $table->unique(['warehouse_id', 'raw_material_id'], 'rm_wh_material_unique');
        });

        // Move existing global balances into a warehouse so per-warehouse
        // tracking starts from the current quantities. Prefer the production
        // warehouse (the consumption point), then any raw-material warehouse.
        // The global quantity column stays as the running total and is kept
        // in step by matching deltas on every scoped movement.
        $targetWarehouseId = DB::table('warehouses')->where('type', 'production')->where('is_active', true)->value('id')
            ?? DB::table('warehouses')->where('type', 'raw_material')->where('is_active', true)->orderBy('id')->value('id');

        if ($targetWarehouseId) {
            $now = now()->toDateTimeString();

            foreach (DB::table('raw_materials')->where('quantity', '>', 0)->get() as $material) {
                DB::table('raw_material_stocks')->insert([
                    'warehouse_id' => $targetWarehouseId,
                    'raw_material_id' => $material->id,
                    'quantity' => $material->quantity,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('raw_material_stocks');
    }
};
