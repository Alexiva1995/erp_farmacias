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
        // 1. Tabla de variantes de productos
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->string('sku')->unique()->nullable();
            $table->string('attribute_type'); // 'size' (talla), 'shade' (tono), 'volume' (volumen), etc.
            $table->string('attribute_value'); // 'Talla 6', 'Rojo Pasión', '100ml'
            $table->decimal('price_modifier', 10, 2)->default(0.00); // Ajuste al precio base (+ o -)
            $table->integer('stock')->default(0);
            $table->timestamps();
        });

        // 2. Tabla de órdenes de e-commerce (independiente del POS físico)
        Schema::create('ecommerce_orders', function (Blueprint $table) {
            $table->id();
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone')->nullable();
            $table->string('shipping_address');
            $table->decimal('total_amount', 12, 2);
            $table->string('status')->default('Pending'); // 'Pending', 'Paid', 'Shipped', 'Delivered', 'Cancelled'
            $table->string('payment_method')->default('Simulated');
            $table->timestamps();
        });

        // 3. Tabla de ítems de órdenes de e-commerce
        Schema::create('ecommerce_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ecommerce_order_id')->constrained('ecommerce_orders')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->onDelete('set null');
            $table->integer('quantity');
            $table->decimal('price', 10, 2); // Precio unitario cobrado
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ecommerce_order_items');
        Schema::dropIfExists('ecommerce_orders');
        Schema::dropIfExists('product_variants');
    }
};
