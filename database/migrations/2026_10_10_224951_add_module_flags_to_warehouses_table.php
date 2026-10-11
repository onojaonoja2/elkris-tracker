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
        Schema::table('warehouses', function (Blueprint $table) {
            $table->boolean('has_production_module')->default(false)->after('type');
            $table->boolean('has_raw_material_module')->default(false)->after('has_production_module');
        });

        // Convert type-based stores into module flags. Primary type falls back
        // to 'state' since 'central' cannot be inferred from existing data.
        DB::table('warehouses')->where('type', 'production')->update([
            'type' => 'state',
            'has_production_module' => true,
        ]);

        DB::table('warehouses')->where('type', 'raw_material')->update([
            'type' => 'state',
            'has_raw_material_module' => true,
        ]);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE warehouses MODIFY type ENUM('central', 'state') NOT NULL DEFAULT 'state'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE warehouses MODIFY type ENUM('central', 'state', 'production', 'raw_material') NOT NULL DEFAULT 'state'");
        }

        DB::table('warehouses')->where('has_production_module', true)->update(['type' => 'production']);
        DB::table('warehouses')->where('has_raw_material_module', true)->update(['type' => 'raw_material']);

        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropColumn(['has_production_module', 'has_raw_material_module']);
        });
    }
};
