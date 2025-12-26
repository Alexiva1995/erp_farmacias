<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Order;

class OrdersLegacyImportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();

        $this->command->info('Truncating orders table...');
        DB::table('orders')->truncate();

        // Read SQL file content
        $sqlPath = database_path('seeders/sql/orders.sql');
        if (!file_exists($sqlPath)) {
            $this->command->error("Archivo SQL no encontrado: {$sqlPath}");
            Schema::enableForeignKeyConstraints();
            return;
        }

        // 14MB is fine for memory in CLI usually
        $this->command->info('Reading SQL file...');
        $sqlContent = file_get_contents($sqlPath);

        // Find all INSERT INTO statements
        // Use a loop to process chunks if there are multiple INSERTs
        // Regex to find "INSERT INTO `orders` (...) VALUES" blocks
        // We capture:
        // 1. Column definitions: (`col1`, `col2`...)
        // 2. Values block: (val1, val2...), (val1, val2...);
        preg_match_all('/INSERT INTO `orders` \((.*?)\) VALUES\s+(.*?);/s', $sqlContent, $insertMatches, PREG_SET_ORDER);

        if (empty($insertMatches)) {
            $this->command->error("No se encontraron sentencias INSERT INTO `orders`.");
            Schema::enableForeignKeyConstraints();
            return;
        }

        $totalOrders = 0;

        foreach ($insertMatches as $matchIndex => $match) {
            $columnsStr = $match[1];
            $valuesBlock = $match[2];

            // Clean columns
            $columns = array_map(function ($col) {
                return trim($col, '` ');
            }, explode(',', $columnsStr));

            // Parse rows in this block
            // Split by "), (" is risky if text contains it, but standard dumps usually escape nicely.
            // Better regex for row splitting:
            preg_match_all('/\((.*?)\)(?:,|$)/s', $valuesBlock, $rows);

            $this->command->info("Procesando bloque {$matchIndex} con " . count($rows[1]) . " filas...");

            $batchData = [];
            foreach ($rows[1] as $rowString) {
                $values = str_getcsv($rowString, ",", "'");

                // Map values to columns
                if (count($columns) !== count($values)) {
                    continue; // Skip malformed rows
                }

                $row = array_combine($columns, $values);
                $row = array_map(function ($val) {
                    return ($val === 'NULL') ? null : $val;
                }, $row);

                // Map to new Order model structure
                $status = $this->mapStatus($row['status'] ?? null);

                // Create or find a legacy cash closing record to satisfy FK
                static $legacyCashClosingId = null;
                if (!$legacyCashClosingId) {
                    // Check if there is at least one user
                    $adminId = DB::table('users')->value('id');

                    $legacyCashClosingId = DB::table('cash_closing')->insertGetId([
                        'seller_id' => $adminId, // FK requires valid user usually
                        'closing_date' => now(),
                        'total_usd' => 0,
                        'total_cop' => 0,
                        'total_bs' => 0,
                        'total_sales' => 0,
                        'status' => 'closed',
                        'created_at' => now(),
                        'updated_at' => now(),
                        // 'daily_closure_id' => null, // Assuming nullable
                    ]);
                }

                $batchData[] = [
                    'id' => $row['id'],
                    'seller_id' => $row['user_id'] ?? null,
                    'client_id' => null,
                    'total_amount' => $row['total_price'] ?? 0,
                    'total_amount_usd' => ($row['currency'] === 'usd') ? ($row['total_price'] ?? 0) : 0,
                    'money_returns' => 0,
                    'total_cost' => 0,
                    'currency' => $row['currency'] ?? 'usd',
                    'usd_conversion' => 1,
                    'has_multiple_currencies' => false,
                    'order_date' => $row['created_at'],
                    'created_at' => $row['created_at'],
                    'updated_at' => $row['updated_at'],
                    'status' => $status,
                    'payment_methods' => isset($row['method_Payment']) ? json_encode(['legacy' => $row['method_Payment']]) : null,
                    'cash_closing_id' => $legacyCashClosingId,
                ];

                $totalOrders++;
            }

            // Insert in chunks to avoid memory issues with large batches
            foreach (array_chunk($batchData, 500) as $chunk) {
                DB::table('orders')->insert($chunk);
            }
        }

        Schema::enableForeignKeyConstraints();
        $this->command->info("Procesamiento completado. Total órdenes migradas: {$totalOrders}");
    }

    private function mapStatus($legacyStatus)
    {
        // 0 - En proceso, 1 - Completada, 2 - Cancelada, 3 - Abandonado
        return match ((int) $legacyStatus) {
            0 => Order::PENDING,
            1 => Order::COMPLETED,
            2 => Order::CANCELLED,
            3 => Order::ABANDONED,
            default => Order::PENDING,
        };
    }
}
