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
        Schema::create('expiration_offer_product_lot', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expiration_offer_id')
                  ->constrained('expiration_offers')
                  ->onDelete('cascade');
            $table->foreignId('product_lot_id')
                  ->constrained('product_lots')
                  ->onDelete('cascade');
            $table->timestamps();

            // Índice único para evitar duplicados
            $table->unique(['expiration_offer_id', 'product_lot_id'], 'exp_offer_lot_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expiration_offer_product_lot');
    }
};
