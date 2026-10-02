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
        Schema::create('expiration_offers', function (Blueprint $table) {
            $table->id();
            $table->integer('months_to_expiration');
            $table->decimal('discount_percentage', 5, 2);
            $table->boolean('is_active')->nullable()->default(true);

            $table->timestamps();

            $table->index('is_active', 'idx_expiration_offer_active');
            $table->index('months_to_expiration', 'idx_expiration_months');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expiration_offers');
    }
};
