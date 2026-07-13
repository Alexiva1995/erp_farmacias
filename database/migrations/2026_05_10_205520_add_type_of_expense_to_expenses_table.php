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
        Schema::table('expenses', function (Blueprint $table) {
            $table->enum('type_of_expense', ['Normal', 'Recurrente'])->default('Normal')->after('is_deductible');
            $table->date('next_expense_date')->nullable()->after('type_of_expense');
            $table->enum('recurrence', ['Mensual', 'Semestral', 'Anual'])->nullable()->after('next_expense_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn(['type_of_expense', 'next_expense_date', 'recurrence']);
        });
    }
};
