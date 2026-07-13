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
        Schema::table('fiscal_history', function (Blueprint $table) {
            $table->decimal('spe_surcharge_rate', 5, 2)->default(0.00)->after('taxable_amount')->comment('El % aplicado Recargo Sujeto Pasivo Especial');
            $table->decimal('spe_surcharge_amount', 15, 2)->default(0.00)->after('spe_surcharge_rate')->comment('Monto del Recargo Sujeto Pasivo Especial');
            $table->decimal('exchange_rate', 15, 2)->default(0.00)->after('spe_surcharge_amount')->comment('tasa aplicada');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fiscal_history', function (Blueprint $table) {
             $table->dropColumn(['spe_surcharge_rate', 'spe_surcharge_amount', 'exchange_rate']);
        });
    }
};
