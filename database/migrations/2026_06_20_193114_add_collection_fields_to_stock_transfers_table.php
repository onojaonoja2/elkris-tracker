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
            $table->timestamp('collected_at')->nullable()->after('approved_at');
            $table->foreignId('collected_by')->nullable()->after('collected_at')->constrained('users')->nullOnDelete();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE stock_transfers MODIFY COLUMN status ENUM('draft','requested','approved','dispatched','received','cancelled','collected') DEFAULT 'draft'");
        }
    }

    public function down(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->dropForeign(['collected_by']);
            $table->dropColumn(['collected_at', 'collected_by']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE stock_transfers MODIFY COLUMN status ENUM('draft','requested','approved','dispatched','received','cancelled') DEFAULT 'draft'");
        }
    }
};
