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
        Schema::table('users', function (Blueprint $table) {
            $table->json('assigned_cities')->nullable();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rep_acceptance_status')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('assigned_cities');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['agent_id']);
            $table->dropColumn(['agent_id', 'rep_acceptance_status']);
        });
    }
};
