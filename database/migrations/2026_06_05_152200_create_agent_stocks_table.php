<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_type_id')->nullable()->constrained('product_types')->nullOnDelete();
            $table->string('product_name');
            $table->integer('grammage');
            $table->integer('quantity')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'product_name', 'grammage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_stocks');
    }
};
