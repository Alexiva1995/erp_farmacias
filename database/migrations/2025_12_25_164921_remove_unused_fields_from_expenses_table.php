<?php

use App\Models\Expense;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('iva');
            $table->dropColumn('invoice_date');
            $table->dropColumn('invoice_number');
            $table->dropColumn('control_number');
            $table->dropColumn('account');
            $table->dropColumn('next_expense_date');
            $table->dropColumn('recurrence');
            $table->dropColumn('type_of_expense');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->boolean('iva')->default(false)->after('is_deductible');
            $table->string('invoice_number', 100)->nullable()->after('has_invoice');
            $table->date('invoice_date')->nullable()->after('invoice_number');
            $table->string('control_number', 100)->nullable()->after('invoice_date');
            $table->string('account', 255)->nullable()->after('count');
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
};
