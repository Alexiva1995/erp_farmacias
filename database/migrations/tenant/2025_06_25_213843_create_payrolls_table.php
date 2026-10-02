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
        Schema::create('payrolls', function (Blueprint $table) {
             $table->id();
            $table->date('payroll_date');
            $table->decimal('amount_total', 15, 2);
            $table->decimal('amount_salary', 15, 2);
            $table->decimal('amount_feeding', 15, 2);
            $table->decimal('amount_transport', 15, 2);
            $table->decimal('amount_medical', 15, 2);
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->index('user_id');
            $table->index('payroll_date', 'idx_payroll_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payrolls');
    }
};
