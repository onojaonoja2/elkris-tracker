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
        Schema::create('production_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('raw_material_id')->constrained('raw_materials')->cascadeOnDelete();
            $table->decimal('quantity_used', 15, 4);
            $table->date('production_date');
            $table->string('output_name');
            $table->decimal('output_quantity', 15, 4);
            $table->string('output_unit');
            $table->string('status')->default('pending_review');
            $table->foreignId('accountant_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accountant_reviewed_at')->nullable();
            $table->text('accountant_notes')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_runs');
    }
};
