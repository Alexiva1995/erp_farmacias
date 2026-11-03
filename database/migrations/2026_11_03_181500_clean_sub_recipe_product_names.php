<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('products') && Schema::hasColumn('products', 'name')) {
            DB::table('products')
                ->where('name', 'like', '%[Preparación Cocina]%')
                ->orWhere('name', 'like', '%[PREPARACIÓN COCINA]%')
                ->orWhere('name', 'like', '%[preparación cocina]%')
                ->update([
                    'name' => DB::raw("TRIM(REPLACE(REPLACE(REPLACE(name, ' [Preparación Cocina]', ''), ' [PREPARACIÓN COCINA]', ''), ' [preparación cocina]', ''))")
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Operación de limpieza no reversible
    }
};
