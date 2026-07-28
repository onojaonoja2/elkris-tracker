<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('damaged_inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('damaged_stock_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_type_id')->constrained()->cascadeOnDelete();
            $table->integer('grammage');
            $table->integer('quantity');
            $table->string('status')->default('in_stock');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('damaged_inventory');
    }
};
