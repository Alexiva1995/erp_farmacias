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
        Schema::create('product_profitability', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->decimal('profitability_percentage', 5, 2)->nullable(false);
            $table->boolean('is_locked')->default(false);
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products');

            $table->index('profitability_percentage', 'idx_product_profitability');
            $table->unique('product_id', 'uniq_product_profit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_profitability');
    }
};
