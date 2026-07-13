<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LaboratoriesLegacyImportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();

        DB::table('laboratories')->truncate();

        // Read SQL file content
        $sqlPath = database_path('seeders/sql/laboratories.sql');
        if (!file_exists($sqlPath)) {
            $this->command->error("Archivo SQL no encontrado: {$sqlPath}");
            Schema::enableForeignKeyConstraints();
            return;
        }

        $sqlContent = file_get_contents($sqlPath);

        // Extract column names from INSERT INTO statement
        // Matches: INSERT INTO `laboratory` (`col1`, `col2`, ...) VALUES
        // Note: Dump table name is `laboratory` (singular), target is `laboratories` (plural).
        preg_match('/INSERT INTO `laboratory` \((.*?)\) VALUES/s', $sqlContent, $matches);

        if (empty($matches[1])) {
            $this->command->error("No se pudo detectar la estructura de columnas en el INSERT.");
            Schema::enableForeignKeyConstraints();
            return;
        }

        // Clean and convert columns to array
        $columns = array_map(function ($col) {
            return trim($col, '` ');
        }, explode(',', $matches[1]));

        $this->command->info("Columnas detectadas: " . implode(', ', $columns));

        // Extract values block
        // Matches content after VALUES until the closing semicolon
        preg_match('/VALUES\s+(.*);/s', $sqlContent, $valuesBlock);

        if (empty($valuesBlock[1])) {
            $this->command->error("No se encontraron valores para insertar.");
            Schema::enableForeignKeyConstraints();
            return;
        }

        // Parse individual rows
        // Regex matches content inside parentheses like (1, 'NAME', ...)
        preg_match_all('/\((.*?)\)(?:,|$)/s', $valuesBlock[1], $rows);

        $totalLabs = 0;

        foreach ($rows[1] as $rowString) {
            // Parse CSV string inside the parentheses
            $values = str_getcsv($rowString, ",", "'");

            // Handle NULL strings
            $values = array_map(function ($val) {
                return ($val === 'NULL') ? null : $val;
            }, $values);

            if (count($columns) !== count($values)) {
                $this->command->warn("Mismatch de columnas/valores en una fila. Saltando.");
                continue;
            }

            $labData = array_combine($columns, $values);

            DB::table('laboratories')->insert([
                'id' => $labData['id'],
                'name' => $labData['name'],
                'created_at' => $labData['created_at'],
                'updated_at' => $labData['updated_at'],
                // 'group_id' is nullable, left as null
            ]);

            $totalLabs++;
        }

        Schema::enableForeignKeyConstraints();

        $this->command->info("Procesamiento completado. Total laboratorios procesados: {$totalLabs}");
    }
}
