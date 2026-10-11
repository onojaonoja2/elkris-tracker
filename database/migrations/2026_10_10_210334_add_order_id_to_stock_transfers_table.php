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
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->after('sales_record_id')->constrained('orders')->nullOnDelete();
        });

        DB::table('stock_transfers')
            ->where('source_type', 'sales_order')
            ->whereNotNull('source_name')
            ->orderBy('id')
            ->each(function (object $transfer): void {
                if (preg_match('/Order #(\d+)/', (string) $transfer->source_name, $matches) && DB::table('orders')->whereKey((int) $matches[1])->exists()) {
                    DB::table('stock_transfers')->whereKey($transfer->id)->update(['order_id' => (int) $matches[1]]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_id');
        });
    }
};
