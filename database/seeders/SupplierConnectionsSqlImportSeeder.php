<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SupplierConnectionsSqlImportSeeder extends Seeder
{
    /**
     * Ejecuta el seeder para importar las conexiones de proveedores desde el archivo SQL.
     */
    public function run(): void
    {
        $sqlPath = database_path('seeders/sql/supplier_connections.sql');
        if (!file_exists($sqlPath)) {
            $this->command?->error("Archivo SQL no encontrado: {$sqlPath}");
            return;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Schema::disableForeignKeyConstraints();

        if (Schema::hasTable('supplier_connections')) {
            $this->command?->info('Limpiando tabla supplier_connections existente...');
            DB::table('supplier_connections')->truncate();
        }

        $sqlContent = file_get_contents($sqlPath);
        DB::unprepared($sqlContent);

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        Schema::enableForeignKeyConstraints();

        $this->command?->info('✅ Conexiones de proveedores importadas exitosamente desde supplier_connections.sql.');
    }
}
