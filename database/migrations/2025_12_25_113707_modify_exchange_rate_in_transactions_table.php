<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Desactivar llaves foráneas temporalmente para evitar bloqueos
        Schema::disableForeignKeyConstraints();

        // 2. Manejo de la columna y la llave foránea
        Schema::table('transactions', function (Blueprint $table) {
            // Si la columna vieja aún existe, intentamos quitar la foránea y renombrar
            if (Schema::hasColumn('transactions', 'exchange_rate_id')) {
                try {
                    // Intentamos varios nombres posibles por si falló antes
                    $table->dropForeign(['exchange_rate_id']);
                    $table->dropForeign('transactions_exchange_rate_id_foreign');
                } catch (\Exception $e) {
                    // Si no existe, no pasa nada
                }
                $table->renameColumn('exchange_rate_id', 'exchange_rate');
            }
        });

        // 3. LIMPIEZA FORZOSA (Soluciona el error 'Data truncated')
        // Convertimos cualquier valor no numérico o NULL a '1.0000' antes del cambio de tipo
        DB::statement("UPDATE transactions SET exchange_rate = '1.0000' WHERE exchange_rate IS NULL OR exchange_rate = '' OR exchange_rate = '0'");
        
        // Convertimos explícitamente a decimal los valores que quedaron
        DB::statement("UPDATE transactions SET exchange_rate = CAST(exchange_rate AS DECIMAL(16,4))");

        // 4. Cambio final de tipo de dato
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('exchange_rate', 16, 4)->default(1.0000)->change();
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'exchange_rate')) {
                $table->renameColumn('exchange_rate', 'exchange_rate_id');
            }
        });

        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'exchange_rate_id')) {
                $table->unsignedBigInteger('exchange_rate_id')->change();
                $table->foreign('exchange_rate_id')->references('id')->on('exchange_rates');
            }
        });
    }
};
