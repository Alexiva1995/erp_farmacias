<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        // 1. Eliminar foreign key solo si existe (fuera del Schema::table)
        try {
            DB::statement('ALTER TABLE transactions DROP FOREIGN KEY transactions_exchange_rate_id_foreign');
        } catch (\Exception $e) {
            // La foreign key no existe, continuamos
        }

        // 2. Renombrar columna (ahora sin foreign key)
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'exchange_rate_id')) {
                $table->renameColumn('exchange_rate_id', 'exchange_rate');
            }
        });

        // 3. Limpieza de datos
        DB::statement("UPDATE transactions SET exchange_rate = '1.0000' WHERE exchange_rate IS NULL OR exchange_rate = '' OR exchange_rate = '0'");
        DB::statement("UPDATE transactions SET exchange_rate = CAST(exchange_rate AS DECIMAL(16,4))");

        // 4. Cambiar tipo de dato
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
