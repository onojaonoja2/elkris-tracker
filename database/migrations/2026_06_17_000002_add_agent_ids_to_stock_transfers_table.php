<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->foreignId('from_agent_id')->nullable()->after('from_stockist_id')->constrained('users')->nullOnDelete();
            $table->foreignId('to_agent_id')->nullable()->after('to_stockist_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->dropForeign(['from_agent_id']);
            $table->dropForeign(['to_agent_id']);
            $table->dropColumn(['from_agent_id', 'to_agent_id']);
        });
    }
};
