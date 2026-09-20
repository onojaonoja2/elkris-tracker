<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_runs', function (Blueprint $table) {
            $table->foreignId('product_type_id')->nullable()->constrained('product_types')->nullOnDelete();
            $table->integer('grammage')->nullable();
            $table->integer('finished_quantity')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('production_runs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_type_id');
            $table->dropColumn(['grammage', 'finished_quantity']);
        });
    }
};
