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
        Schema::create('stockist_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stockist_id')->constrained()->cascadeOnDelete();
            $table->string('product_name');
            $table->integer('grammage');
            $table->integer('quantity')->default(0);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->timestamps();
            $table->unique(['stockist_id', 'product_name', 'grammage']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stockist_stocks');
    }
};
