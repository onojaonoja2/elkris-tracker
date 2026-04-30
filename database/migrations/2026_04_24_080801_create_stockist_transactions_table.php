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
        Schema::create('stockist_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stockist_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('field_agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('trial_order_id')->nullable()->constrained('trial_orders')->nullOnDelete();
            $table->enum('type', ['received', 'deducted', 'manual']);
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('description')->nullable();
            $table->date('transaction_date')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stockist_transactions');
    }
};
