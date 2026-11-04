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
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (Schema::hasColumn('products', 'unit_cost')) {
                    $table->decimal('unit_cost', 15, 4)->default(0.0000)->change();
                }
                if (Schema::hasColumn('products', 'sale_price')) {
                    $table->decimal('sale_price', 15, 4)->default(0.0000)->change();
                }
            });
        }

        if (Schema::hasTable('dish_ingredients')) {
            Schema::table('dish_ingredients', function (Blueprint $table) {
                if (Schema::hasColumn('dish_ingredients', 'designated_cost')) {
                    $table->decimal('designated_cost', 15, 4)->default(0.0000)->change();
                }
            });
        }

        if (Schema::hasTable('product_lots')) {
            Schema::table('product_lots', function (Blueprint $table) {
                if (Schema::hasColumn('product_lots', 'unit_cost')) {
                    $table->decimal('unit_cost', 15, 4)->default(0.0000)->change();
                }
            });
        }

        if (Schema::hasTable('production_orders')) {
            Schema::table('production_orders', function (Blueprint $table) {
                if (Schema::hasColumn('production_orders', 'unit_cost_calculated')) {
                    $table->decimal('unit_cost_calculated', 15, 4)->default(0.0000)->change();
                }
                if (Schema::hasColumn('production_orders', 'total_production_cost')) {
                    $table->decimal('total_production_cost', 15, 4)->default(0.0000)->change();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (Schema::hasColumn('products', 'unit_cost')) {
                    $table->decimal('unit_cost', 10, 2)->default(0.00)->change();
                }
                if (Schema::hasColumn('products', 'sale_price')) {
                    $table->decimal('sale_price', 10, 2)->default(0.00)->change();
                }
            });
        }

        if (Schema::hasTable('dish_ingredients')) {
            Schema::table('dish_ingredients', function (Blueprint $table) {
                if (Schema::hasColumn('dish_ingredients', 'designated_cost')) {
                    $table->decimal('designated_cost', 10, 2)->default(0.00)->change();
                }
            });
        }

        if (Schema::hasTable('product_lots')) {
            Schema::table('product_lots', function (Blueprint $table) {
                if (Schema::hasColumn('product_lots', 'unit_cost')) {
                    $table->decimal('unit_cost', 10, 2)->default(0.00)->change();
                }
            });
        }

        if (Schema::hasTable('production_orders')) {
            Schema::table('production_orders', function (Blueprint $table) {
                if (Schema::hasColumn('production_orders', 'unit_cost_calculated')) {
                    $table->decimal('unit_cost_calculated', 14, 2)->default(0.00)->change();
                }
                if (Schema::hasColumn('production_orders', 'total_production_cost')) {
                    $table->decimal('total_production_cost', 14, 2)->default(0.00)->change();
                }
            });
        }
    }
};
