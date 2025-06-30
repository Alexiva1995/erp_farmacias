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
        Schema::create('order_details', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->foreignId('pack_id')->nullable()->constrained('product_packs')->nullOnDelete();
            $table->enum('product_type', ['normal', 'offer', 'pack']);
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');

            $table->integer('quantity');
            $table->decimal('price', 12, 2);
            $table->decimal('unit_cost', 12, 2);

            $table->timestamps();

            $table->index('product_id');
            $table->index('pack_id');
            $table->index('order_id', 'idx_order_detail');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_details');
    }
};
