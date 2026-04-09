<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'lead_id')) {
                $table->foreignId('lead_id')->nullable()->change();
            }
            if (Schema::hasColumn('customers', 'rep_id')) {
                $table->foreignId('rep_id')->nullable()->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'lead_id')) {
                $table->foreignId('lead_id')->nullable(false)->change();
            }
            if (Schema::hasColumn('customers', 'rep_id')) {
                $table->foreignId('rep_id')->nullable(false)->change();
            }
        });
    }
};
