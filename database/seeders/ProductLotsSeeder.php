<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ProductLotsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $json = File::get(database_path('data/product_lots.json'));
        $lots = json_decode($json, true);

        foreach ($lots as &$lot) {
            $lot['lot_number'] = $lot['lot'];
            $lot['cost_price'] = $lot['cost'] ?? 0;
            $lot['quantity'] = $lot['quantity_available'] ?? 0;
            $lot['expiration_date'] = $lot['expiration_date'] ?? '1900-01-01';

            unset($lot['lot'], $lot['cost'], $lot['quantity_available']);
        }

        foreach (array_chunk($lots, 500) as $chunk) {
            DB::table('product_lots')->insert($chunk);
        }

    }
}
