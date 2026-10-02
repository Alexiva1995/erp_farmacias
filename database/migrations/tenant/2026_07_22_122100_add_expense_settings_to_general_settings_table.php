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
        Schema::table('general_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('general_settings', 'expense_mode')) {
                $table->string('expense_mode')->default('real')->after('profitability_calculation_type');
            }
            if (!Schema::hasColumn('general_settings', 'expense_auto_approve')) {
                $table->boolean('expense_auto_approve')->default(false)->after('expense_mode');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('general_settings', function (Blueprint $table) {
            if (Schema::hasColumn('general_settings', 'expense_mode')) {
                $table->dropColumn('expense_mode');
            }
            if (Schema::hasColumn('general_settings', 'expense_auto_approve')) {
                $table->dropColumn('expense_auto_approve');
            }
        });
    }
};
