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
        Schema::create('doctor_offer_scales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_offer_id')->constrained('doctor_offers')->onDelete('cascade');
            $table->integer('min_volume');
            $table->integer('max_volume')->nullable();
            $table->decimal('discount_percentage', 5, 2);
            $table->timestamps();

            $table->index('doctor_offer_id');
            $table->index(['min_volume', 'max_volume'], 'idx_doctor_scale_volume');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctor_offer_scales');
    }
};
