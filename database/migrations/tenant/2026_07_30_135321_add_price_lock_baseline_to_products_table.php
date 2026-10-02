<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega la columna price_lock_baseline para guardar el precio base
     * de referencia al momento de añadir un producto a la orden desde el asistente IA.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('price_lock_baseline', 12, 4)->nullable()->comment('Precio base de referencia al añadir a la orden (asistente IA)');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('price_lock_baseline');
        });
    }
};
