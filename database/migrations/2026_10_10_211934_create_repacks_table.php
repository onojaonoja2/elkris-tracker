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
        Schema::create('repacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('input_product_type_id')->constrained('product_types')->cascadeOnDelete();
            $table->integer('input_grammage');
            $table->integer('input_quantity');
            $table->foreignId('output_product_type_id')->constrained('product_types')->cascadeOnDelete();
            $table->integer('output_grammage');
            $table->integer('output_quantity');
            $table->string('status')->default('posted');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('repacks');
    }
};
