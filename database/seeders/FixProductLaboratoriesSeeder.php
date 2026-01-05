<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class FixProductLaboratoriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Loading products.json...');
        $jsonPath = database_path('data/products.json');

        if (!File::exists($jsonPath)) {
            $this->command->error("File not found: {$jsonPath}");
            return;
        }

        $json = File::get($jsonPath);
        $productsJson = json_decode($json, true);

        // Build map: Product ID => Laboratory ID (only if set in JSON)
        $jsonMap = [];
        foreach ($productsJson as $p) {
            if (isset($p['laboratory_id']) && $p['laboratory_id']) {
                $jsonMap[$p['id']] = $p['laboratory_id'];
            }
        }

        $this->command->info("Loaded " . count($jsonMap) . " product-laboratory associations from JSON.");

        // Load existing valid Laboratory IDs
        $validLabIds = DB::table('laboratories')->pluck('id')->flip()->toArray();

        // Find products with NULL laboratory_id
        $productsToFix = DB::table('products')->whereNull('laboratory_id')->orderBy('id')->cursor();

        $updatedCount = 0;
        $missingLabCount = 0;

        foreach ($productsToFix as $product) {
            if (isset($jsonMap[$product->id])) {
                $jsonLabId = $jsonMap[$product->id];

                // Check if this Lab ID actually exists in our DB
                if (isset($validLabIds[$jsonLabId])) {
                    DB::table('products')
                        ->where('id', $product->id)
                        ->update(['laboratory_id' => $jsonLabId]);
                    $updatedCount++;
                } else {
                    $missingLabCount++;
                }
            }
        }

        $this->command->info("Seeder completed.");
        $this->command->info("Updated products: {$updatedCount}");
        if ($missingLabCount > 0) {
            $this->command->warn("Products skipped (Laboratory ID found in JSON but missing in DB): {$missingLabCount}");
        }
    }
}
