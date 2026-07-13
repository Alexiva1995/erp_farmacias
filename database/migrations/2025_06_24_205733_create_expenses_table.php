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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('category_id')->constrained('expense_categories')->onDelete('cascade');
            $table->decimal('amount', 15, 2);
            $table->decimal('amount_usd', 15, 2);
            $table->string('currency', 10);
            $table->boolean('has_invoice')->nullable()->default(false);
            $table->boolean('is_deductible')->nullable()->default(false);
            $table->date('expense_date');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->index('category_id');
            $table->index('user_id');
            $table->index('expense_date', 'idx_expense_date');
            $table->index('is_deductible', 'idx_expense_deductible');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
