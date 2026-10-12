<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Buscar el proveedor Dorsumca por coincidencia de nombre
        $dorsumcaSuppliers = DB::table('suppliers')
            ->where('name', 'LIKE', '%DORSUMCA%')
            ->pluck('id');

        foreach ($dorsumcaSuppliers as $supplierId) {
            $hasFileConn = DB::table('supplier_connections')
                ->where('supplier_id', $supplierId)
                ->where('type', 'file')
                ->exists();

            if ($hasFileConn) {
                // Si ya tiene una conexión 'file', eliminar la conexión 'api' obsoleta
                DB::table('supplier_connections')
                    ->where('supplier_id', $supplierId)
                    ->where('type', 'api')
                    ->delete();
            } else {
                // Si solo tenía la de tipo 'api', convertirla a 'file'
                DB::table('supplier_connections')
                    ->where('supplier_id', $supplierId)
                    ->where('type', 'api')
                    ->update([
                        'type' => 'file',
                        'name' => DB::raw("CONCAT(COALESCE(name, 'Dorsumca'), ' (Excel)')"),
                    ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No requiere rollback destructivo
    }
};
