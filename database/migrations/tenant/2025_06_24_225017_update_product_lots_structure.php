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
        Schema::table('product_lots', function (Blueprint $table) {
            $table->string('lot_number')->nullable(false)->change();
            $table->renameColumn('cost_price', 'unit_cost');

            $table->index('expiration_date', 'idx_lot_expiration');
            $table->index('product_id', 'idx_lot_product');
            $table->index('supplier_id', 'idx_lot_supplier');
            $table->index('lot_number', 'idx_lot_number');
            $table->index(['product_id', 'expiration_date'], 'idx_lot_product_exp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
         Schema::table('products', function (Blueprint $table) {
            $table->string('lot_number')->nullable()->change();
            $table->renameColumn('unit_cost', 'cost_price');
            $table->dropColumn('amount_usd');

            $table->dropIndex('idx_lot_expiration');
            $table->dropIndex('idx_lot_product');
            $table->dropIndex('idx_lot_supplier');
            $table->dropIndex('idx_lot_number');
            $table->dropIndex('idx_lot_product_exp');
        });
    }
};
