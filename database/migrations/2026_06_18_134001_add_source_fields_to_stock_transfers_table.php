<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->string('source_type')->default('warehouse')->after('from_agent_id');
            $table->string('source_name')->nullable()->after('source_type');
            $table->string('dispatch_papers_path')->nullable()->after('source_name');
            $table->boolean('requires_approval')->default(false)->after('dispatch_papers_path');
        });
    }

    public function down(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->dropColumn(['source_type', 'source_name', 'dispatch_papers_path', 'requires_approval']);
        });
    }
};
