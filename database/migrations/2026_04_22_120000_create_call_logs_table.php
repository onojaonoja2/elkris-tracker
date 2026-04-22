<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->timestamp('called_at')->useCurrent();
            $table->enum('outcome', ['connected', 'voicemail', 'not_reachable', 'wrong_number', 'callback', 'no_answer']);
            $table->text('notes')->nullable();
            $table->text('other_comment')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'called_at']);
            $table->index(['customer_id', 'called_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_logs');
    }
};
