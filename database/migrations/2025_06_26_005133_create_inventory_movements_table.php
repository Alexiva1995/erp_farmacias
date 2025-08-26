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
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('product_lot_id')->nullable()->constrained('product_lots')->nullOnDelete();
            $table->enum('movement_type', ['return', 'sale', 'purchase', 'adjustment', 'loss', 'expired']);
            $table->integer('quantity')->comment('Positivo = entrada, Negativo = salida');

            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->integer('stock_before');
            $table->integer('stock_after');
            $table->dateTime('movement_date');

            $table->timestamps();

            $table->index('supplier_id');
            $table->index('user_id');
            $table->index('movement_date', 'idx_movement_date');
            $table->index('movement_type', 'idx_movement_type');
            $table->index('product_id', 'idx_movement_product');
            $table->index('product_lot_id', 'idx_movement_lot');
            $table->index(['movement_date', 'movement_type'], 'idx_movement_date_type');
            $table->index('invoice_id', 'fk_inventory_movements_invoice_id');
            $table->index('order_id', 'fk_inventory_movements_order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
