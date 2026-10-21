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
        // Buscar el proveedor Drosymca / Dorsumca por coincidencia de nombre
        $drosymcaSuppliers = DB::table('suppliers')
            ->where(function ($query) {
                $query->where('name', 'LIKE', '%DROSYMCA%')
                      ->orWhere('name', 'LIKE', '%DORSUMCA%');
            })
            ->pluck('id');

        foreach ($drosymcaSuppliers as $supplierId) {
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
                $updateData = ['type' => 'file'];
                if (\Illuminate\Support\Facades\Schema::hasColumn('supplier_connections', 'name')) {
                    $updateData['name'] = DB::raw("CONCAT(COALESCE(name, 'Dorsumca'), ' (Excel)')");
                }
                DB::table('supplier_connections')
                    ->where('supplier_id', $supplierId)
                    ->where('type', 'api')
                    ->update($updateData);
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
