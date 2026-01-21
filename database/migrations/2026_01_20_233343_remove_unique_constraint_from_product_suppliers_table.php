<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

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
        
        // Obtener el nombre real de la foreign key
        $foreignKeys = DB::select("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'product_suppliers' 
            AND COLUMN_NAME = 'product_id'
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ");
        
        foreach ($foreignKeys as $fk) {
            DB::statement("ALTER TABLE product_suppliers DROP FOREIGN KEY {$fk->CONSTRAINT_NAME}");
        }
        
        // Eliminar el índice único
        DB::statement("ALTER TABLE product_suppliers DROP INDEX uniq_product_supplier");
        
        // Recrear la foreign key sin el índice único
        DB::statement("
            ALTER TABLE product_suppliers 
            ADD CONSTRAINT product_suppliers_product_id_foreign 
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        ");
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
