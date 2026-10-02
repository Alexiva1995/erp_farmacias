<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega columnas de desglose de Bs al consolidado diario
     * para mantener la misma granularidad que cash_closing individual.
     */
    public function up(): void
    {
        Schema::table('daily_closures', function (Blueprint $table) {
            $table->decimal('bs_cash', 15, 2)->default(0)->after('bs_mobile');
            $table->decimal('bs_card_debito', 15, 2)->default(0)->after('bs_cash');
            $table->decimal('bs_card_credit', 15, 2)->default(0)->after('bs_card_debito');
            $table->decimal('bs_transfer', 15, 2)->default(0)->after('bs_card_credit');
        });
    }

    public function down(): void
    {
        Schema::table('daily_closures', function (Blueprint $table) {
            $table->dropColumn(['bs_cash', 'bs_card_debito', 'bs_card_credit', 'bs_transfer']);
        });
    }
};
