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
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->decimal('source_amount', 14, 2)->nullable()->after('amount');
            $table->string('source_currency', 10)->nullable()->after('source_amount');
            $table->decimal('exchange_rate_applied', 12, 4)->nullable()->after('source_currency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->dropColumn(['source_amount', 'source_currency', 'exchange_rate_applied']);
        });
    }
};
