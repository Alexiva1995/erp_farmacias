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
        Schema::create('auto_order_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('auto_orders')->onDelete('cascade');
            $table->foreignId('product_suppliers_id')->constrained('product_suppliers')->onDelete('cascade');

            $table->integer('quantity');
            $table->decimal('unit_cost', 10, 2);
            $table->decimal('subtotal', 10, 2);

            $table->timestamps();

            $table->index('product_suppliers_id', 'product_suppliers_id');
            $table->index('order_id', 'idx_auto_detail_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auto_order_details');
    }
};
