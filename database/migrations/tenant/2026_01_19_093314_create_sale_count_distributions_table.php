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
        Schema::create('sale_count_distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_count_id')
                ->constrained('sales_counts')
                ->onDelete('cascade');
            $table->foreignId('product_lot_id')
                ->constrained('product_lots')
                ->onDelete('cascade');
            $table->integer('quantity');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_count_distributions');
    }
};
