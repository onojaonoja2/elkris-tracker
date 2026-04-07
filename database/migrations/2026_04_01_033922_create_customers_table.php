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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            
            // Foreign Keys (Managed via Filament BelongsToSelect or Select)
            $table->foreignId('lead_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('rep_id')->constrained('users')->cascadeOnDelete();

            // Core Data Fields
            $table->string('customer_name');
            $table->string('phone_number')->nullable();
            $table->integer('age')->nullable();
            $table->string('gender')->nullable();
            $table->string('city')->nullable();
            $table->text('address')->nullable();
            
            // Status & Classification (Handled by Filament Select/Toggles)
            // $table->string('status')->default('draft');
            $table->string('customer_status')->nullable();
            $table->string('diabetic_awareness')->nullable();
            
            // Interaction & Logistics
            $table->date('call_date')->nullable();
            $table->string('preffered_call_time')->nullable();
            $table->text('feedback')->nullable();
            $table->text('remarks')->nullable();
            $table->date('follow_up_date')->nullable();
            
            // Order details
            $table->integer('order_quantity')->default(0);
            $table->text('delivery_details')->nullable();
            $table->string('delivery_status')->nullable();

            // Reordering (Used by Filament's reorderable table feature)
            $table->integer('sort')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
