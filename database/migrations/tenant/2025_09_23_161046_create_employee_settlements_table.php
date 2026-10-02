<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('employee_settlements', function (Blueprint $table) {
            $table->id();
            $table->decimal('currency', 15, 2);
            $table->decimal('social_benefits_days', 15, 2);
            $table->decimal('social_benefits_amount', 15, 2);
            $table->decimal('vacation_voucher_days', 15, 2);
            $table->decimal('vacation_voucher_amount', 15, 2);
            $table->decimal('vacation_bonus_voucher_days', 15, 2);
            $table->decimal('vacation_bonus_voucher_amount', 15, 2);
            $table->decimal('earnings_voucher_days', 15, 2);
            $table->decimal('earnings_voucher_amount', 15, 2);
            $table->decimal('total_settlement', 15, 2);
            $table->decimal('vacation_voucher_deduction', 15, 2);
            $table->decimal('vacation_bonus_voucher_deduction', 15, 2);
            $table->decimal('earnings_voucher_deduction', 15, 2);
            $table->decimal('total_deduction', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->decimal('percentage', 15, 2);
            $table->decimal('total', 15, 2);
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_settlements');
    }
};
