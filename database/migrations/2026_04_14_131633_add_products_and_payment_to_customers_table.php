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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('product_name');
            $table->integer('grammage')->comment('Weight in grammes');
            $table->integer('quantity')->default(1);
            $table->decimal('price', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('preferred_payment_option')->nullable()->after('delivery_status');
            $table->decimal('total_price', 12, 2)->default(0)->after('preferred_payment_option');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['preferred_payment_option', 'total_price']);
        });

        Schema::dropIfExists('products');
    }
};
