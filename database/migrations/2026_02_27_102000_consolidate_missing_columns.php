<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('is_deleted');
            }
        });

        Schema::table('product_lots', function (Blueprint $table) {
            if (!Schema::hasColumn('product_lots', 'amount_usd')) {
                $table->decimal('amount_usd', 15, 2)->default(0.00)->after('unit_cost');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });

        Schema::table('product_lots', function (Blueprint $table) {
            if (Schema::hasColumn('product_lots', 'amount_usd')) {
                $table->dropColumn('amount_usd');
            }
        });
    }
};
