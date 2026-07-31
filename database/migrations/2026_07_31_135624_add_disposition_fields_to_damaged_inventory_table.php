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
        Schema::table('damaged_inventory', function (Blueprint $table) {
            $table->foreignId('destination_warehouse_id')->nullable()->after('warehouse_id')->constrained('warehouses')->nullOnDelete();
            $table->foreignId('dispatched_by')->nullable()->after('destination_warehouse_id')->constrained('users')->nullOnDelete();
            $table->timestamp('dispatched_at')->nullable()->after('dispatched_by');
            $table->foreignId('received_by')->nullable()->after('dispatched_at')->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable()->after('received_by');
            $table->foreignId('destroyed_by')->nullable()->after('received_at')->constrained('users')->nullOnDelete();
            $table->timestamp('destroyed_at')->nullable()->after('destroyed_by');
            $table->string('destroy_reason')->nullable()->after('destroyed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('damaged_inventory', function (Blueprint $table) {
            $table->dropConstrainedForeignId('destination_warehouse_id');
            $table->dropConstrainedForeignId('dispatched_by');
            $table->dropColumn('dispatched_at');
            $table->dropConstrainedForeignId('received_by');
            $table->dropColumn('received_at');
            $table->dropConstrainedForeignId('destroyed_by');
            $table->dropColumn('destroyed_at');
            $table->dropColumn('destroy_reason');
        });
    }
};
