<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('trial_orders', 'receipt_path')) {
            Schema::table('trial_orders', function (Blueprint $table) {
                $table->string('receipt_path')->nullable()->after('products');
                $table->string('receipt_original_name')->nullable()->after('receipt_path');
                $table->timestamp('accountant_verified_at')->nullable()->after('payment_status');
                $table->foreignId('accountant_verified_by')->nullable()->constrained('users')->nullOnDelete()->after('accountant_verified_at');
                $table->timestamp('supervisor_verified_at')->nullable()->after('accountant_verified_by');
                $table->foreignId('supervisor_verified_by')->nullable()->constrained('users')->nullOnDelete()->after('supervisor_verified_at');
                $table->text('accountant_notes')->nullable()->after('supervisor_verified_by');
                $table->text('supervisor_notes')->nullable()->after('accountant_notes');
            });
        }

        if (! Schema::hasColumn('stockists', 'type')) {
            Schema::table('stockists', function (Blueprint $table) {
                $table->boolean('is_trial_order_marketer')->default(false)->after('supervisor_id');
                $table->string('type', 50)->default('stockist_only')->after('supervisor_id');
            });
        }

        if (! Schema::hasColumn('products', 'product_type_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->foreignId('product_type_id')->nullable()->constrained('product_types')->nullOnDelete()->after('order_id');
            });
        }

        if (! Schema::hasColumn('stockist_stocks', 'product_type_id')) {
            Schema::table('stockist_stocks', function (Blueprint $table) {
                $table->foreignId('product_type_id')->nullable()->constrained('product_types')->nullOnDelete()->after('stockist_id');
            });
        }

        if (! Schema::hasColumn('stock_transactions', 'product_type_id')) {
            Schema::table('stock_transactions', function (Blueprint $table) {
                $table->foreignId('product_type_id')->nullable()->constrained('product_types')->nullOnDelete()->after('product_name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('stock_transactions', 'product_type_id')) {
            Schema::table('stock_transactions', function (Blueprint $table) {
                $table->dropForeign(['product_type_id']);
                $table->dropColumn('product_type_id');
            });
        }

        if (Schema::hasColumn('stockist_stocks', 'product_type_id')) {
            Schema::table('stockist_stocks', function (Blueprint $table) {
                $table->dropForeign(['product_type_id']);
                $table->dropColumn('product_type_id');
            });
        }

        if (Schema::hasColumn('products', 'product_type_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropForeign(['product_type_id']);
                $table->dropColumn('product_type_id');
            });
        }

        if (Schema::hasColumn('stockists', 'type')) {
            Schema::table('stockists', function (Blueprint $table) {
                $table->dropColumn(['type', 'is_trial_order_marketer']);
            });
        }

        if (Schema::hasColumn('trial_orders', 'receipt_path')) {
            Schema::table('trial_orders', function (Blueprint $table) {
                $table->dropForeign(['accountant_verified_by']);
                $table->dropForeign(['supervisor_verified_by']);
                $table->dropColumn([
                    'receipt_path', 'receipt_original_name',
                    'accountant_verified_at', 'accountant_verified_by',
                    'supervisor_verified_at', 'supervisor_verified_by',
                    'accountant_notes', 'supervisor_notes',
                ]);
            });
        }
    }
};
