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
        Schema::create('production_run_raw_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('raw_material_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity_used', 20, 4);
            $table->timestamps();
        });

        DB::table('production_runs')
            ->whereNotNull('raw_material_id')
            ->orderBy('id')
            ->chunk(100, function ($runs) {
                foreach ($runs as $run) {
                    DB::table('production_run_raw_materials')->insert([
                        'production_run_id' => $run->id,
                        'raw_material_id' => $run->raw_material_id,
                        'quantity_used' => $run->quantity_used,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });

        Schema::table('production_runs', function (Blueprint $table) {
            $table->dropForeign(['raw_material_id']);
            $table->dropColumn(['raw_material_id', 'quantity_used']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_runs', function (Blueprint $table) {
            $table->foreignId('raw_material_id')->nullable()->constrained()->nullOnDelete()->after('id');
            $table->decimal('quantity_used', 20, 4)->nullable()->after('raw_material_id');
        });

        DB::table('production_run_raw_materials')
            ->orderBy('id')
            ->chunk(100, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('production_runs')
                        ->where('id', $row->production_run_id)
                        ->update([
                            'raw_material_id' => $row->raw_material_id,
                            'quantity_used' => $row->quantity_used,
                        ]);
                }
            });

        Schema::dropIfExists('production_run_raw_materials');
    }
};
