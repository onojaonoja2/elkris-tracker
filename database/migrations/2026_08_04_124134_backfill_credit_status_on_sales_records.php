<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('sales_records')
            ->where('is_credit', true)
            ->whereNull('credit_status')
            ->update(['credit_status' => 'pending_payment']);
    }

    public function down(): void
    {
        DB::table('sales_records')
            ->where('is_credit', true)
            ->where('credit_status', 'pending_payment')
            ->update(['credit_status' => null]);
    }
};
