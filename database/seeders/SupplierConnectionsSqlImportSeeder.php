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
        Schema::disableForeignKeyConstraints();

        $sqlPath = database_path('seeders/sql/supplier_connections.sql');
        if (!file_exists($sqlPath)) {
            $this->command?->error("Archivo SQL no encontrado: {$sqlPath}");
            Schema::enableForeignKeyConstraints();
            return;
        }

        $sqlContent = file_get_contents($sqlPath);

        if (!Schema::hasTable('supplier_connections')) {
            $this->command?->info('La tabla supplier_connections no existe. Creándola desde el volcado SQL...');
            DB::unprepared($sqlContent);
            $this->command?->info('✅ Tabla supplier_connections creada y poblada exitosamente.');
        } else {
            $this->command?->info('Limpiando tabla supplier_connections existente...');
            DB::table('supplier_connections')->truncate();

            if (preg_match('/INSERT INTO `supplier_connections`\s*\((.*?)\)\s*VALUES\s*(.*?);/s', $sqlContent, $matches)) {
                DB::unprepared($matches[0]);
                $this->command?->info('✅ Conexiones de proveedores importadas exitosamente desde supplier_connections.sql.');
            } else {
                DB::unprepared($sqlContent);
                $this->command?->info('✅ Script SQL ejecutado.');
            }
        }

        Schema::enableForeignKeyConstraints();
    }
}
