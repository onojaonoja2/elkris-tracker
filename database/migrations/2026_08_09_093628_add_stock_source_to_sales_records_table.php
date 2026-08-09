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
        Schema::table('sales_records', function (Blueprint $table) {
            $table->string('stock_source')->nullable()->after('status');
        });

        DB::table('sales_records')
            ->whereIn('agent_type', ['open_market', 'retail_market'])
            ->whereNull('stock_source')
            ->update(['stock_source' => 'warehouse']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_records', function (Blueprint $table) {
            $table->dropColumn('stock_source');
        });
    }
};
