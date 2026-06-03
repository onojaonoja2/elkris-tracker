<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_transfer_items', function (Blueprint $table) {
            $table->integer('rejected_quantity')->default(0)->after('quantity');
            $table->text('rejection_reason')->nullable()->after('rejected_quantity');
            $table->timestamp('rejection_resolved_at')->nullable()->after('rejection_reason');
        });
    }

    public function down(): void
    {
        Schema::table('stock_transfer_items', function (Blueprint $table) {
            $table->dropColumn(['rejected_quantity', 'rejection_reason', 'rejection_resolved_at']);
        });
    }
};
