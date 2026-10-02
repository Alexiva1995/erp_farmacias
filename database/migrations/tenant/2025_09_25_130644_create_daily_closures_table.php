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
        Schema::create('daily_closures', function (Blueprint $table) {
            $table->id();
            $table->decimal('total_usd', 15, 2)->default(0.00);
            $table->decimal('total_cop', 15, 2)->default(0.00);
            $table->decimal('total_bs', 15, 2)->default(0.00);

            $table->decimal('bs_card', 15, 2)->default(0.00);
            $table->decimal('bs_mobile', 15, 2)->default(0.00);

            $table->decimal('usd_delivered', 15, 2)->default(0.00);
            $table->decimal('cop_delivered', 15, 2)->default(0.00);
            $table->decimal('bs_delivered', 15, 2)->default(0.00);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_closures');
    }
};
