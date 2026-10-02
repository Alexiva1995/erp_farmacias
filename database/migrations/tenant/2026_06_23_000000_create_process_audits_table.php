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
        Schema::create('process_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->foreignId('cashier_id')->constrained('employees')->onDelete('restrict');
            $table->foreignId('cook_id')->constrained('employees')->onDelete('restrict');
            $table->integer('phase_attention_seconds')->default(0);
            $table->integer('phase_dough_seconds')->default(0);
            $table->integer('phase_plating_seconds')->default(0);
            $table->integer('phase_icecream_seconds')->default(0);
            $table->integer('phase_beverage_seconds')->default(0);
            $table->integer('phase_delivery_seconds')->default(0);
            $table->integer('total_seconds')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('process_audits');
    }
};
