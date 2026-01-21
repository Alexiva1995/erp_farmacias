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
        // Primero necesitamos eliminar las foreign keys que dependen del índice único
        // Luego eliminar el índice único
        // Y finalmente recrear las foreign keys sin el índice único
        
        Schema::table('product_suppliers', function (Blueprint $table) {
            // Eliminar la foreign key de product_id primero
            $table->dropForeign(['product_id']);
        });
        
        Schema::table('product_suppliers', function (Blueprint $table) {
            // Ahora sí podemos eliminar el índice único
            $table->dropUnique('uniq_product_supplier');
        });
        
        Schema::table('product_suppliers', function (Blueprint $table) {
            // Recrear la foreign key de product_id sin el índice único
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     * Restaura la restricción única (en caso de necesitar hacer rollback)
     */
    public function down(): void
    {
        // Para restaurar, primero eliminamos la foreign key
        Schema::table('product_suppliers', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
        });
        
        // Restaurar el índice único
        Schema::table('product_suppliers', function (Blueprint $table) {
            $table->unique(['product_id', 'supplier_id'], 'uniq_product_supplier');
        });
        
        // Recrear la foreign key
        Schema::table('product_suppliers', function (Blueprint $table) {
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->onDelete('cascade');
        });
    }
};
