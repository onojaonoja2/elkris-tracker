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
        Schema::table('sales_records', function (Blueprint $table) {
            $table->string('payment_proof_path')->nullable()->after('receipt_path');
            $table->foreignId('payment_proof_uploaded_by')->nullable()->constrained('users')->nullOnDelete()->after('payment_proof_path');
            $table->timestamp('payment_proof_uploaded_at')->nullable()->after('payment_proof_uploaded_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_records', function (Blueprint $table) {
            $table->dropColumn(['payment_proof_path', 'payment_proof_uploaded_by', 'payment_proof_uploaded_at']);
        });
    }
};
