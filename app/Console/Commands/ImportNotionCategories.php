<?php

namespace App\Console\Commands;

use App\Models\Category;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportNotionCategories extends Command
{
    /**
     * El nombre y firma del comando en consola.
     */
    protected $signature = 'app:import-categories {--truncate : Vaciar categorías antes de importar}';

    /**
     * La descripción del comando.
     */
    protected $description = 'Importa y registra únicamente las categorías desde el archivo produc.csv';

    /**
     * Ejecutar el comando.
     */
    public function handle()
    {
        $filePath = base_path('produc.csv');

        if (!file_exists($filePath)) {
            $this->error("❌ El archivo {$filePath} no existe.");
            return 1;
        }

        if ($this->option('truncate')) {
            $this->warn("⚠️ Opción --truncate detectada. Vaciando tabla de categorías...");
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            Category::truncate();
            
            // Establecer el inicio de los IDs de categorías en 10000
            DB::statement('ALTER TABLE categories AUTO_INCREMENT = 10000;');
            
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            $this->info("✅ Tabla de categorías vaciada y contador de ID reiniciado en 10000.");
        }

        $this->info("🚀 Leyendo categorías desde {$filePath}...");

        $file = fopen($filePath, 'r');
        
        // Leer la primera línea para obtener la cabecera
        $rawHeaders = fgetcsv($file, 0, ',');
        if (!$rawHeaders) {
            $this->error("❌ No se pudo leer la cabecera del archivo.");
            fclose($file);
            return 1;
        }

        // Limpiar cabeceras de posibles espacios, comillas y caracteres especiales ocultos (BOM)
        $headers = array_map(function($header) {
            $clean = preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $header);
            return trim(str_replace(['"', "'"], '', $clean));
        }, $rawHeaders);

        // Detectar la columna de Categoría
        $categoryKey = null;
        foreach ($headers as $header) {
            $upper = mb_strtoupper($header, 'UTF-8');
            $normalized = strtr($upper, [
                'Á'=>'A', 'É'=>'E', 'Í'=>'I', 'Ó'=>'O', 'Ú'=>'U',
                'á'=>'A', 'é'=>'E', 'í'=>'I', 'ó'=>'O', 'ú'=>'U'
            ]);

            if (str_contains($normalized, 'CATEG')) {
                $categoryKey = $header;
                break;
            }
        }

        if (!$categoryKey) {
            $this->error("❌ No se pudo encontrar una columna de categoría en el CSV.");
            fclose($file);
            return 1;
        }

        $this->info("Columnas detectadas:");
        $this->info("- Categoría: {$categoryKey}");

        $created = 0;
        $existed = 0;
        $processedCategories = [];

        DB::beginTransaction();

        try {
            while (($row = fgetcsv($file, 0, ',')) !== false) {
                if (count($row) < count($headers) || empty(trim($row[0]))) {
                    continue;
                }

                $rowData = array_combine($headers, array_slice($row, 0, count($headers)));
                $categoryName = trim($rowData[$categoryKey] ?? '');

                if (empty($categoryName)) {
                    continue;
                }

                $categoryNameClean = mb_convert_case($categoryName, MB_CASE_UPPER, "UTF-8");

                // Evitar buscar repetidamente en la misma corrida
                if (in_array($categoryNameClean, $processedCategories)) {
                    continue;
                }

                $processedCategories[] = $categoryNameClean;

                // Buscar o Crear categoría
                $category = Category::where('name', $categoryNameClean)->first();
                if (!$category) {
                    Category::create([
                        'name' => $categoryNameClean
                    ]);
                    $this->info("➕ Categoría creada: {$categoryNameClean}");
                    $created++;
                } else {
                    $existed++;
                }
            }

            DB::commit();
            fclose($file);

            $this->info("✅ Importación de categorías finalizada.");
            $this->info("🆕 Nuevas categorías registradas: {$created}");
            $this->info("♻️ Categorías que ya existían: {$existed}");

            return 0;

        } catch (\Exception $e) {
            DB::rollBack();
            fclose($file);
            $this->error("❌ Error durante la importación: " . $e->getMessage());
            return 1;
        }
    }
}
