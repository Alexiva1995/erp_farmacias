<?php

use App\Models\Expense;
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
        Schema::table('expenses', function (Blueprint $table) {
            //

            $table->date('expense_date')->nullable()->change();
            $table->date('next_expense_date')->nullable();
            $table->enum('recurrence', [
                Expense::RECURRENCE_MENSUAL,
                Expense::RECURRENCE_SEMESTRAL,
                Expense::RECURRENCE_ANUAL
            ])->nullable();
            $table->enum('type_of_expense', [
                Expense::TYPE_OF_EXPENSE_NORMAL,
                Expense::TYPE_OF_EXPENSE_RECURRENTE
            ])->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            //
        });
    }
};
