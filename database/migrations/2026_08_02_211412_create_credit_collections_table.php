<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_record_id')->constrained()->cascadeOnDelete();
            $table->decimal('collected_amount', 12, 2)->default(0);
            $table->timestamp('collected_at')->nullable();
            $table->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('payment_proof_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('sales_record_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_collections');
    }
};
