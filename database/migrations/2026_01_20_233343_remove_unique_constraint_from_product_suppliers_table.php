<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Elimina la restricción única que impide insertar múltiples productos
     * con product_id NULL y el mismo supplier_id
     */
    public function up(): void
    {
        Schema::table('product_suppliers', function (Blueprint $table) {
            // Eliminar el índice único que bloquea las inserciones
            $table->dropUnique('uniq_product_supplier');
        });
    }

    /**
     * Reverse the migrations.
     * Restaura la restricción única (en caso de necesitar hacer rollback)
     */
    public function down(): void
    {
        Schema::table('product_suppliers', function (Blueprint $table) {
            // Restaurar el índice único
            $table->unique(['product_id', 'supplier_id'], 'uniq_product_supplier');
        });
    }
};
