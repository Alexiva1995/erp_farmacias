<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Buscar la conexión de Dronena (ID 27 o por nombre)
        $connection = DB::table('supplier_connections')
            ->where('supplier_id', 27)
            ->first();

        if ($connection && $connection->structure) {
            $structure = json_decode($connection->structure, true);
            
            // Mapear la columna 6 (letra G) como discount_percentage
            // Basado en el formato estándar de Dronena
            $structure['6'] = [
                'type' => 'decimal',
                'target' => 'discount_percentage',
                'file_field' => 'G'
            ];
            
            DB::table('supplier_connections')
                ->where('supplier_id', 27)
                ->update(['structure' => json_encode($structure)]);
            
            Log::info("Migración: Mapeo de Dronena actualizado con columna de descuento.");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = DB::table('supplier_connections')
            ->where('supplier_id', 27)
            ->first();

        if ($connection && $connection->structure) {
            $structure = json_decode($connection->structure, true);
            
            if (isset($structure['6']) && $structure['6']['target'] === 'discount_percentage') {
                unset($structure['6']);
                
                DB::table('supplier_connections')
                    ->where('supplier_id', 27)
                    ->update(['structure' => json_encode($structure)]);
            }
        }
    }
};
