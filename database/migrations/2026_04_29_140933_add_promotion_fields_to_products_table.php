<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->enum('promotion_type', ['buy_2_get_1_free'])->nullable()->after('price');
            $table->integer('free_quantity')->default(0)->after('promotion_type');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['promotion_type', 'free_quantity']);
        });
    }
};
