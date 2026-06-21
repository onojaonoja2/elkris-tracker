<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('agent_type')->nullable();
            $table->json('products')->nullable();
            $table->decimal('total_value', 12, 2)->default(0);
            $table->string('vendor_name')->nullable();
            $table->string('business_name')->nullable();
            $table->string('receipt_path')->nullable();
            $table->string('receipt_original_name')->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('stockist_id')->nullable()->constrained('stockists')->nullOnDelete();
            $table->decimal('stockist_balance', 12, 2)->default(0);
            $table->timestamp('accountant_verified_at')->nullable();
            $table->foreignId('accountant_verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('supervisor_verified_at')->nullable();
            $table->foreignId('supervisor_verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('accountant_notes')->nullable();
            $table->text('supervisor_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_records');
    }
};
