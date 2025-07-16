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
        Schema::create('payrolls_details', function (Blueprint $table) {
            $table->id();

            $table->foreignId('payroll_id')->constrained('payrolls')->onDelete('cascade');
            $table->decimal('food_bonus', 15, 2);
            $table->decimal('transport_bonus', 15, 2)->nullable()->default(0.00);
            $table->decimal('medical_bonus', 15, 2)->nullable()->default(0.00);
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('photo_url')->nullable();

            $table->timestamps();

            $table->index('user_id');
            $table->index('payroll_id', 'idx_payroll_detail');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payrolls_details');
    }
};
