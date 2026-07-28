<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->index('role');
            $table->index('lead_id');
            $table->index('portfolio_agent_id');
            $table->index(['role', 'is_active']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->index('customer_status');
            $table->index('rep_acceptance_status');
            $table->index('follow_up_date');
            $table->index(['lead_id', 'rep_acceptance_status']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index('status');
            $table->index('user_id');
            $table->index(['status', 'created_at']);
        });

        Schema::table('trial_orders', function (Blueprint $table) {
            $table->index('status');
            $table->index('agent_id');
            $table->index(['status', 'created_at']);
        });

        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->index('status');
            $table->index(['status', 'created_at']);
        });

        Schema::table('sales_records', function (Blueprint $table) {
            $table->index('status');
            $table->index(['status', 'created_at']);
        });

        Schema::table('call_logs', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('customer_id');
            $table->index('called_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropIndex(['lead_id']);
            $table->dropIndex(['portfolio_agent_id']);
            $table->dropIndex(['role', 'is_active']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['customer_status']);
            $table->dropIndex(['rep_acceptance_status']);
            $table->dropIndex(['follow_up_date']);
            $table->dropIndex(['lead_id', 'rep_acceptance_status']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['status', 'created_at']);
        });

        Schema::table('trial_orders', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['agent_id']);
            $table->dropIndex(['status', 'created_at']);
        });

        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['status', 'created_at']);
        });

        Schema::table('sales_records', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['status', 'created_at']);
        });

        Schema::table('call_logs', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['customer_id']);
            $table->dropIndex(['called_at']);
        });
    }
};
