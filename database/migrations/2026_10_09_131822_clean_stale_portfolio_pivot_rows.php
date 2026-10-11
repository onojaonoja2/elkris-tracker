<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('customer_rep')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('customers')
                    ->whereColumn('customers.id', 'customer_rep.customer_id')
                    ->whereColumn('customers.rep_id', 'customers.lead_id');
            })
            ->delete();

        DB::table('customer_lead')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('customers')
                    ->whereColumn('customers.id', 'customer_lead.customer_id')
                    ->whereColumn('customers.rep_id', 'customers.lead_id')
                    ->whereColumn('customers.lead_id', '<>', 'customer_lead.user_id');
            })
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
