<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('role', 'field_agent')
            ->update(['role' => 'direct_sales']);
    }

    public function down(): void
    {
        DB::table('users')
            ->where('role', 'direct_sales')
            ->update(['role' => 'field_agent']);
    }
};
