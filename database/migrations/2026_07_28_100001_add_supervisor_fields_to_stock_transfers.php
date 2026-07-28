<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->foreignId('supervisor_approved_by')->nullable()->constrained('users')->nullOnDelete()->after('approved_at');
            $table->timestamp('supervisor_approved_at')->nullable()->after('supervisor_approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->dropForeign(['supervisor_approved_by']);
            $table->dropColumn(['supervisor_approved_by', 'supervisor_approved_at']);
        });
    }
};
