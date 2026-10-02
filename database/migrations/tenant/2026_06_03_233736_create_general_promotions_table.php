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
        Schema::create('general_promotions', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // '2x1', '3x2', '50_second', 'fixed_price'
            $table->decimal('fixed_price', 10, 2)->nullable();
            $table->boolean('is_active')->default(false);
            $table->json('categories')->nullable(); // Array de IDs de categorías
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('general_promotions');
    }
};
