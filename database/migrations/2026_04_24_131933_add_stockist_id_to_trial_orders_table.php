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
        Schema::table('trial_orders', function (Blueprint $table) {
            $table->foreignId('stockist_id')->nullable()->constrained('stockists')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trial_orders', function (Blueprint $table) {
            $table->dropForeign(['stockist_id']);
            $table->dropColumn('stockist_id');
        });
    }
};
