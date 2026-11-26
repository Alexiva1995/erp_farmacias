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
        if (!Schema::hasTable('category_profitability')) {
            return;
        }

        Schema::table('category_profitability', function (Blueprint $table) {
            if (Schema::hasColumn('category_profitability', 'profitability_percentage')) {
                $table->decimal('profitability_percentage', 5, 2)->nullable()->change();
            }
            if (!Schema::hasColumn('category_profitability', 'shipping_cost')) {
                $table->decimal('shipping_cost', 10, 2)->nullable()->after('profitability_percentage');
            }
            if (!Schema::hasColumn('category_profitability', 'packaging_cost')) {
                $table->decimal('packaging_cost', 10, 2)->nullable()->after('shipping_cost');
            }
            if (!Schema::hasColumn('category_profitability', 'expense_margin')) {
                $table->decimal('expense_margin', 5, 2)->nullable()->after('packaging_cost');
            }
            if (!Schema::hasColumn('category_profitability', 'profit_margin')) {
                $table->decimal('profit_margin', 5, 2)->nullable()->after('expense_margin');
            }
            if (!Schema::hasColumn('category_profitability', 'tax_usa')) {
                $table->decimal('tax_usa', 8, 2)->nullable()->after('profit_margin');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('category_profitability')) {
            return;
        }

        Schema::table('category_profitability', function (Blueprint $table) {
            $columnsToDrop = [];
            foreach (['shipping_cost', 'packaging_cost', 'expense_margin', 'profit_margin', 'tax_usa'] as $column) {
                if (Schema::hasColumn('category_profitability', $column)) {
                    $columnsToDrop[] = $column;
                }
            }

            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
