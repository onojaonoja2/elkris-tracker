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
            $table->string('payment_status')->default('pending')->after('status');
            $table->decimal('agent_balance', 12, 2)->default(0)->after('payment_status');
            $table->decimal('stockist_balance', 12, 2)->default(0)->after('agent_balance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trial_orders', function (Blueprint $table) {
            $table->dropColumn(['payment_status', 'agent_balance', 'stockist_balance']);
        });
    }
};
