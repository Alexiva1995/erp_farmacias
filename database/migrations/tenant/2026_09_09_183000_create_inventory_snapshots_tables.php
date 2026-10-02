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
        Schema::create('inventory_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('cutoff_date');
            $table->integer('period_days')->default(30);
            $table->integer('total_products')->default(0);
            $table->decimal('total_inventory_units', 15, 2)->default(0);
            $table->decimal('total_inventory_value', 15, 2)->default(0);
            $table->decimal('total_sales_units', 15, 2)->default(0);
            $table->decimal('total_sales_value', 15, 2)->default(0);
            $table->integer('overstock_products_count')->default(0);
            $table->decimal('overstock_inventory_value', 15, 2)->default(0);
            $table->boolean('is_automatic')->default(false);
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();

            $table->index('cutoff_date');
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('inventory_snapshot_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inventory_snapshot_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('product_name');
            $table->string('laboratory_name')->nullable();
            $table->string('sales_class', 2)->default('C');
            $table->decimal('sold_units_30d', 12, 2)->default(0);
            $table->decimal('total_sales_usd_30d', 15, 2)->default(0);
            $table->decimal('current_stock_units', 12, 2)->default(0);
            $table->decimal('unit_cost_usd', 15, 4)->default(0);
            $table->decimal('sale_price_usd', 15, 4)->default(0);
            $table->decimal('inventory_value_usd', 15, 2)->default(0);
            $table->decimal('margin_percentage', 8, 2)->default(0);
            $table->decimal('coverage_days', 10, 2)->default(999);
            $table->decimal('gmroi_annual_percentage', 10, 2)->default(0);
            $table->integer('days_to_expiration')->nullable();
            $table->boolean('is_overstock')->default(false);
            $table->timestamps();

            $table->foreign('inventory_snapshot_id')
                ->references('id')
                ->on('inventory_snapshots')
                ->cascadeOnDelete();

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->nullOnDelete();

            $table->index(['inventory_snapshot_id', 'product_id'], 'idx_snap_prod');
            $table->index(['inventory_snapshot_id', 'sales_class'], 'idx_snap_class');
            $table->index(['inventory_snapshot_id', 'is_overstock'], 'idx_snap_overstock');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_snapshot_items');
        Schema::dropIfExists('inventory_snapshots');
    }
};
