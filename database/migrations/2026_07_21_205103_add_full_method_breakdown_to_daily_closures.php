<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega una columna por cada método de pago posible a daily_closures,
     * replicando la misma granularidad que tiene cash_closing individual.
     * Así el consolidado diario puede mostrar el desglose exacto por método
     * sin importar si estaba habilitado o no en el TPV.
     */
    public function up(): void
    {
        Schema::table('daily_closures', function (Blueprint $table) {
            // USD — métodos individuales
            $table->decimal('usd_cash', 15, 2)->default(0)->after('usd_delivered');
            $table->decimal('usd_transfer', 15, 2)->default(0)->after('usd_cash');
            $table->decimal('usd_paypal', 15, 2)->default(0)->after('usd_transfer');
            $table->decimal('usd_binance', 15, 2)->default(0)->after('usd_paypal');

            // COP — métodos individuales
            $table->decimal('cop_cash', 15, 2)->default(0)->after('cop_delivered');
            $table->decimal('cop_transfer', 15, 2)->default(0)->after('cop_cash');
        });
    }

    public function down(): void
    {
        Schema::table('daily_closures', function (Blueprint $table) {
            $table->dropColumn([
                'usd_cash', 'usd_transfer', 'usd_paypal', 'usd_binance',
                'cop_cash', 'cop_transfer',
            ]);
        });
    }
};
