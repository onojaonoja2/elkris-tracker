<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_records', function (Blueprint $table) {
            $table->boolean('is_credit')->default(false);
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->date('expected_collection_date')->nullable();
            $table->string('credit_status')->nullable();
            $table->timestamp('collected_at')->nullable();
            $table->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('credit_notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sales_records', function (Blueprint $table) {
            $table->dropColumn([
                'is_credit',
                'customer_name',
                'customer_phone',
                'expected_collection_date',
                'credit_status',
                'collected_at',
                'collected_by',
                'credit_notes',
            ]);
        });
    }
};
