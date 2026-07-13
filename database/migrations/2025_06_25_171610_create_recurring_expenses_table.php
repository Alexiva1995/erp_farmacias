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
        Schema::create('recurring_expenses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('category_id')->constrained('expense_categories')->onDelete('cascade');
            $table->decimal('amount', 15, 2);
            $table->string('currency', 10);
            $table->date('start_date');
            $table->enum('frequency', ['daily', 'weekly', 'monthly', 'yearly']);
            $table->date('last_generated')->nullable();
            $table->timestamps();

            $table->index('category_id');
            $table->index('frequency', 'idx_recurring_frequency');
            $table->index('start_date', 'idx_recurring_start');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_expenses');
    }
};
