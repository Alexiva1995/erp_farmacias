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
        Schema::disableForeignKeyConstraints();

        $sqlPath = database_path('seeders/sql/suppliers.sql');
        if (!file_exists($sqlPath)) {
            $this->command?->error("Archivo SQL no encontrado: {$sqlPath}");
            Schema::enableForeignKeyConstraints();
            return;
        }

        $sqlContent = file_get_contents($sqlPath);

        // Extraer la sentencia INSERT INTO `suppliers`
        if (preg_match('/INSERT INTO `suppliers`\s*\((.*?)\)\s*VALUES\s*(.*?);/s', $sqlContent, $matches)) {
            $this->command?->info('Limpiando tabla suppliers existente...');
            DB::table('suppliers')->truncate();

            $insertQuery = $matches[0];
            DB::unprepared($insertQuery);

            $this->command?->info('✅ Proveedores importados exitosamente desde suppliers.sql.');
        } else {
            // Si no coincide con regex, intentar ejecutar DB::unprepared con el contenido SQL
            $this->command?->warn('Ejecutando volcado SQL directamente...');
            DB::unprepared($sqlContent);
            $this->command?->info('✅ Script SQL ejecutado.');
        }

        Schema::enableForeignKeyConstraints();
    }
}
