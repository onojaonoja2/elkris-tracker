<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->foreignId('requested_by')->nullable()->after('to_stockist_id')->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->after('requested_by')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('received_at');
            $table->text('rejection_reason')->nullable()->after('notes');
        });

        if (config('database.default') === 'mysql') {
            DB::statement("ALTER TABLE stock_transfers MODIFY COLUMN status ENUM('draft','requested','approved','dispatched','received','cancelled') NOT NULL DEFAULT 'draft'");
        }
    }

    public function down(): void
    {
        if (config('database.default') === 'mysql') {
            DB::statement("ALTER TABLE stock_transfers MODIFY COLUMN status ENUM('draft','dispatched','received','cancelled') NOT NULL DEFAULT 'draft'");
        }

        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->dropForeign(['requested_by']);
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['requested_by', 'approved_by', 'approved_at', 'rejection_reason']);
        });
    }
};
