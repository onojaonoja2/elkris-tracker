<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_records', function (Blueprint $table) {
            $table->timestamp('proof_review_requested_at')->nullable();
            $table->foreignId('proof_review_requested_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('proof_review_requested_by');
            $table->dropColumn('proof_review_requested_at');
        });
    }
};
