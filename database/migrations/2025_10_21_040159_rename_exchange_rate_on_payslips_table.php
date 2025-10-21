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
        Schema::table('payslips', function (Blueprint $table) {
            $table->dropForeign(['exchange_rate_id']);
            $table->dropColumn('exchange_rate_id');
            $table->decimal('exchange_rate', 10, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            $table->foreignId('exchange_rate_id')->nullable()->constrained()->cascadeOnDelete();
            $table->dropColumn('exchange_rate');
        });
    }
};
