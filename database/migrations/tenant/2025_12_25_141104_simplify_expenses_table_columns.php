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
            // 1. Eliminar amount_bs
            if (Schema::hasColumn('expenses', 'amount_bs')) {
                $table->dropColumn('amount_bs');
            }

            // 2. Eliminar amount_usd
            if (Schema::hasColumn('expenses', 'amount_usd')) {
                $table->dropColumn('amount_usd');
            }

            // 3. Renombrar conversion_rate_to_bs a conversion_rate
            if (Schema::hasColumn('expenses', 'conversion_rate_to_bs')) {
                $table->renameColumn('conversion_rate_to_bs', 'conversion_rate');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            // Revertir el nombre de la tasa
            if (Schema::hasColumn('expenses', 'conversion_rate')) {
                $table->renameColumn('conversion_rate', 'conversion_rate_to_bs');
            }

            // Volver a crear las columnas eliminadas por si haces rollback
            $table->decimal('amount_bs', 15, 2)->nullable();
            $table->decimal('amount_usd', 15, 2)->nullable();
        });
    }
};
