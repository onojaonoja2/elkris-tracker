<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sales_records', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('agent_type')->constrained('warehouses')->nullOnDelete();
        });

        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->foreignId('sales_record_id')->nullable()->after('source_name')->constrained('sales_records')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->dropForeign(['sales_record_id']);
            $table->dropColumn('sales_record_id');
        });

        Schema::table('sales_records', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn('warehouse_id');
        });
    }
};
