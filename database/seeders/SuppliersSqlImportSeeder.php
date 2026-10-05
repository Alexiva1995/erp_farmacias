<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SuppliersSqlImportSeeder extends Seeder
{
    /**
     * Ejecuta el seeder para importar los proveedores desde el archivo SQL.
     */
    public function run(): void
    {
        $sqlPath = database_path('seeders/sql/suppliers.sql');
        if (!file_exists($sqlPath)) {
            $this->command?->error("Archivo SQL no encontrado: {$sqlPath}");
            return;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Schema::disableForeignKeyConstraints();

        if (Schema::hasTable('suppliers')) {
            $this->command?->info('Limpiando tabla suppliers existente...');
            DB::table('suppliers')->truncate();
        }

        $sqlContent = file_get_contents($sqlPath);
        DB::unprepared($sqlContent);

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        Schema::enableForeignKeyConstraints();

        $this->command?->info('✅ Proveedores importados exitosamente desde suppliers.sql.');
    }
}
