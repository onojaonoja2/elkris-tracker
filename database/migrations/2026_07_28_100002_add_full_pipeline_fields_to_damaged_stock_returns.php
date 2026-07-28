<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('damaged_stock_returns', function (Blueprint $table) {
            $table->foreignId('supervisor_approved_by')->nullable()->constrained('users')->nullOnDelete()->after('approved_at');
            $table->timestamp('supervisor_approved_at')->nullable()->after('supervisor_approved_by');
            $table->foreignId('accountant_approved_by')->nullable()->constrained('users')->nullOnDelete()->after('supervisor_approved_at');
            $table->timestamp('accountant_approved_at')->nullable()->after('accountant_approved_by');
            $table->timestamp('return_to_warehouse_initiated_at')->nullable()->after('accountant_approved_at');
            $table->foreignId('return_to_warehouse_initiated_by')->nullable()->constrained('users')->nullOnDelete()->after('return_to_warehouse_initiated_at');
            $table->timestamp('return_received_at')->nullable()->after('return_to_warehouse_initiated_by');
            $table->foreignId('return_received_by')->nullable()->constrained('users')->nullOnDelete()->after('return_received_at');
        });
    }

    public function down(): void
    {
        Schema::table('damaged_stock_returns', function (Blueprint $table) {
            $table->dropForeign(['supervisor_approved_by']);
            $table->dropForeign(['accountant_approved_by']);
            $table->dropForeign(['return_to_warehouse_initiated_by']);
            $table->dropForeign(['return_received_by']);
            $table->dropColumn([
                'supervisor_approved_by',
                'supervisor_approved_at',
                'accountant_approved_by',
                'accountant_approved_at',
                'return_to_warehouse_initiated_at',
                'return_to_warehouse_initiated_by',
                'return_received_at',
                'return_received_by',
            ]);
        });
    }
};
