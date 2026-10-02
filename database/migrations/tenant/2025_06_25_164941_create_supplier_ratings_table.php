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
        Schema::create('supplier_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->onDelete('cascade');
            $table->date('rating_date');
            $table->decimal('product_arrival', 5, 2);
            $table->decimal('delivery_time', 5, 2);
            $table->decimal('returns', 5, 2);
            $table->decimal('amount_ratio', 5, 2);
            $table->decimal('unit_ratio', 5, 2);
            $table->decimal('overall_rating', 5, 2);
            $table->timestamps();

            $table->index('supplier_id');
            $table->index('rating_date', 'idx_rating_date');
            $table->index('overall_rating', 'idx_overall_rating');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_ratings');
    }
};
