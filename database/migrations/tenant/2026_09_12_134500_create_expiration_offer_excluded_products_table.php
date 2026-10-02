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
        Schema::create('expiration_offer_excluded_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expiration_offer_id')
                  ->constrained('expiration_offers')
                  ->cascadeOnDelete();
            $table->foreignId('product_id')
                  ->constrained('products')
                  ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['expiration_offer_id', 'product_id'], 'exp_offer_prod_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expiration_offer_excluded_products');
    }
};
