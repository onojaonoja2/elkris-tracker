<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE products MODIFY COLUMN promotion_type ENUM('buy_2_get_1_free', 'buy_3_get_1_free') NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE products MODIFY COLUMN promotion_type ENUM('buy_2_get_1_free') NULL");
    }
};
