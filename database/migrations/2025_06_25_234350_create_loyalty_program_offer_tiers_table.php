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
        Schema::create('loyalty_program_offer_tiers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('loyalty_program_offer_id')
                  ->constrained('loyalty_program_offers')
                  ->onDelete('cascade');

            $table->integer('min_volume');
            $table->integer('max_volume');

            $table->timestamps();

            $table->index('loyalty_program_offer_id');
            $table->index(['min_volume', 'max_volume'], 'idx_tier_volume');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loyalty_program_offer_tiers');
    }
};
