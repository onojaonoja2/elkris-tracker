<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->timestamp('rejected_at')->nullable()->after('rejection_note');
            $table->foreignId('rejected_by')->nullable()->after('rejected_at')->constrained('users')->nullOnDelete();
            $table->boolean('needs_replacement')->default(false)->after('rejected_by');
            $table->foreignId('replacement_requested_by')->nullable()->after('needs_replacement')->constrained('users')->nullOnDelete();
            $table->timestamp('replacement_requested_at')->nullable()->after('replacement_requested_by');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['rejected_by']);
            $table->dropForeign(['replacement_requested_by']);
            $table->dropColumn(['rejected_at', 'rejected_by', 'needs_replacement', 'replacement_requested_by', 'replacement_requested_at']);
        });
    }
};
