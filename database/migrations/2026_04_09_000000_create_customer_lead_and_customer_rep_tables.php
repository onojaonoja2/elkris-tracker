<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customer_lead', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['customer_id', 'user_id']);
        });

        Schema::create('customer_rep', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['customer_id', 'user_id']);
        });

        // Migrate existing scalar lead_id/rep_id values into pivots
        if (Schema::hasTable('customers')) {
            $customers = DB::table('customers')->select('id', 'lead_id', 'rep_id')->get();

            foreach ($customers as $c) {
                if (! empty($c->lead_id)) {
                    DB::table('customer_lead')->updateOrInsert(
                        ['customer_id' => $c->id, 'user_id' => $c->lead_id],
                        ['created_at' => now(), 'updated_at' => now()]
                    );
                }

                if (! empty($c->rep_id)) {
                    DB::table('customer_rep')->updateOrInsert(
                        ['customer_id' => $c->id, 'user_id' => $c->rep_id],
                        ['created_at' => now(), 'updated_at' => now()]
                    );
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_rep');
        Schema::dropIfExists('customer_lead');
    }
};
