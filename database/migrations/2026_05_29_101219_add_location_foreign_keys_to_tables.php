<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add location FKs to stockists table
        Schema::table('stockists', function (Blueprint $table) {
            $table->foreignId('state_id')->nullable()->constrained('states')->nullOnDelete();
            $table->foreignId('lga_id')->nullable()->constrained('lgas')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
        });

        // Add location FKs to customers table
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('state_id')->nullable()->constrained('states')->nullOnDelete();
            $table->foreignId('lga_id')->nullable()->constrained('lgas')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
        });

        // Add lga_id to users table for assigned areas
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('lga_id')->nullable()->after('lead_id')->constrained('lgas')->nullOnDelete();
        });

        // Add lga_id to orders table
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('lga_id')->nullable()->after('customer_id')->constrained('lgas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $t) => $t->dropConstrainedForeignId('lga_id'));
        Schema::table('users', fn (Blueprint $t) => $t->dropConstrainedForeignId('lga_id'));
        Schema::table('customers', function (Blueprint $t) {
            $t->dropConstrainedForeignId('city_id');
            $t->dropConstrainedForeignId('lga_id');
            $t->dropConstrainedForeignId('state_id');
        });
        Schema::table('stockists', function (Blueprint $t) {
            $t->dropConstrainedForeignId('city_id');
            $t->dropConstrainedForeignId('lga_id');
            $t->dropConstrainedForeignId('state_id');
        });
    }
};
