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
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->decimal('profitability_percentage', 5, 2);
            $table->boolean('is_locked')->nullable()->default(false);
            $table->timestamps();

            $table->unique('product_id', 'uniq_product_profit');
            $table->index('profitability_percentage', 'idx_product_profitability');
    
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
