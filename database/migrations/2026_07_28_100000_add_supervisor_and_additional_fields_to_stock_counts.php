<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_counts', function (Blueprint $table) {
            $table->boolean('is_additional_count')->default(false)->after('warehouse_id');
            $table->foreignId('parent_stock_count_id')->nullable()->constrained('stock_counts')->cascadeOnDelete()->after('is_additional_count');
            $table->string('supervisor_status')->nullable()->after('status');
            $table->foreignId('supervisor_verified_by')->nullable()->constrained('users')->nullOnDelete()->after('supervisor_status');
            $table->timestamp('supervisor_verified_at')->nullable()->after('supervisor_verified_by');
        });
    }

    public function down(): void
    {
        Schema::table('stock_counts', function (Blueprint $table) {
            $table->dropForeign(['parent_stock_count_id']);
            $table->dropForeign(['supervisor_verified_by']);
            $table->dropColumn(['is_additional_count', 'parent_stock_count_id', 'supervisor_status', 'supervisor_verified_by', 'supervisor_verified_at']);
        });
    }
};
