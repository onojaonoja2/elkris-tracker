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
        Schema::table('raw_material_receipts', function (Blueprint $table) {
            $table->string('source_type')->default('supplier')->after('warehouse_id');
            $table->text('dispatch_papers_path')->nullable()->after('items');
            $table->string('supplier_name', 200)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('raw_material_receipts', function (Blueprint $table) {
            $table->dropColumn(['source_type', 'dispatch_papers_path']);
            $table->string('supplier_name', 200)->nullable(false)->change();
        });
    }
};
