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
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->constrained()->cascadeOnDelete();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropColumn('customer_id');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->json('lifetime_purchases')->nullable();
            $table->dropColumn([
                'delivery_status',
                'preferred_payment_option',
                'total_price',
                'preferred_delivery_date',
                'delivery_details',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropColumn('order_id');

            $table->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('lifetime_purchases');

            $table->string('delivery_status')->nullable();
            $table->string('preferred_payment_option')->nullable();
            $table->decimal('total_price', 12, 2)->default(0);
            $table->date('preferred_delivery_date')->nullable();
            $table->text('delivery_details')->nullable();
        });
    }
};
