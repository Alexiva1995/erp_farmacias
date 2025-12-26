<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrderDetailsLegacyImportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();

        $this->command->info('Truncating order_details table...');
        DB::table('order_details')->truncate();

        // Read SQL file content
        $sqlPath = database_path('seeders/sql/order_details.sql');
        if (!file_exists($sqlPath)) {
            $this->command->error("Archivo SQL no encontrado: {$sqlPath}");
            Schema::enableForeignKeyConstraints();
            return;
        }

        // Pre-load product prices to avoid N+1 queries
        $this->command->info('Loading product prices...');
        $productPrices = DB::table('products')->pluck('sale_price', 'id')->toArray();

        // 16MB file - should be safe for memory
        $this->command->info('Reading SQL file...');
        $sqlContent = file_get_contents($sqlPath);

        // Find all INSERT INTO statements
        // Regex to find "INSERT INTO `order_product` (...) VALUES" blocks
        // Note: Legacy table name in SQL is `order_product`, new is `order_details`
        preg_match_all('/INSERT INTO `order_product` \((.*?)\) VALUES\s+(.*?);/s', $sqlContent, $insertMatches, PREG_SET_ORDER);

        if (empty($insertMatches)) {
            $this->command->error("No se encontraron sentencias INSERT INTO `order_product`.");
            Schema::enableForeignKeyConstraints();
            return;
        }

        $totalDetails = 0;

        foreach ($insertMatches as $matchIndex => $match) {
            $columnsStr = $match[1];
            $valuesBlock = $match[2];

            // Clean columns
            $columns = array_map(function ($col) {
                return trim($col, '` ');
            }, explode(',', $columnsStr));

            // Parse rows in this block
            preg_match_all('/\((.*?)\)(?:,|$)/s', $valuesBlock, $rows);

            $this->command->info("Procesando bloque {$matchIndex} con " . count($rows[1]) . " filas...");

            $batchData = [];
            foreach ($rows[1] as $rowString) {
                $values = str_getcsv($rowString, ",", "'");

                if (count($columns) !== count($values)) {
                    continue;
                }

                $row = array_combine($columns, $values);
                $row = array_map(function ($val) {
                    $val = trim($val); // Trim whitespace
                    return ($val === 'NULL' || $val === '') ? null : $val;
                }, $row);

                // Mapping Logic
                // price_total -> price (default 0 if null)
                $price = $row['price_total'] ?? 0;
                $productId = $row['product_id'];

                // Logic for unit_cost based on user request:
                // If we don't have a cost from legacy (we don't), take sale_price from Product relation.
                // If product not found, default to 0.
                $unitCost = $productPrices[$productId] ?? 0;

                $batchData[] = [
                    'id' => $row['id'],
                    'order_id' => $row['order_id'],
                    'product_id' => $productId,
                    'quantity' => $row['quantity'],
                    'price' => $price,
                    // New fields defaults
                    'unit_cost' => $unitCost,
                    'unit_price_usd' => 0,
                    'quantity_expiration' => 0,
                    'discount_percentage' => 0,
                    'discount_type' => null,
                    'product_type' => $row['type_products'] ?? 1, // Defaulting to 1 (assuming normal) just in case
                    'created_at' => $row['created_at'] ?? now(),
                    'updated_at' => $row['updated_at'] ?? now(),
                    // 'pack_id' => $row['pack_id'] ?? null,
                ];

                $totalDetails++;
            }

            // Insert in chunks
            foreach (array_chunk($batchData, 500) as $chunk) {
                DB::table('order_details')->insert($chunk);
            }
        }

        Schema::enableForeignKeyConstraints();
        $this->command->info("Procesamiento completado. Total detalles migrados: {$totalDetails}");
    }
}
