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
        Schema::table('profitability_settings', function (Blueprint $table) {
            $table->decimal('shipping_cost', 10, 2)->default(0)->nullable();
            $table->decimal('packaging_cost', 10, 2)->default(0)->nullable();
            $table->decimal('expense_margin', 5, 2)->default(0)->nullable();
            $table->decimal('profit_margin', 5, 2)->default(0)->nullable();
        });

        Schema::table('product_profitability', function (Blueprint $table) {
            $table->decimal('shipping_cost', 10, 2)->nullable();
            $table->decimal('packaging_cost', 10, 2)->nullable();
            $table->decimal('expense_margin', 5, 2)->nullable();
            $table->decimal('profit_margin', 5, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('profitability_settings', function (Blueprint $table) {
            $table->dropColumn(['shipping_cost', 'packaging_cost', 'expense_margin', 'profit_margin']);
        });

        Schema::table('product_profitability', function (Blueprint $table) {
            $table->dropColumn(['shipping_cost', 'packaging_cost', 'expense_margin', 'profit_margin']);
        });
    }
};
