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
        $data = [];

        $products = DB::table('products')->pluck('id')->flip();

        foreach ($lots as &$lot) {
            if ($products->has($lot['product_id'])) {
                $data[] = [
                    'product_id' => $lot['product_id'],
                    'lot_number' => $lot['lot'] ?? '',
                    'unit_cost' => $lot['cost'] ?? 0,
                    'quantity' => $lot['quantity_available'] ?? 0,
                    'expiration_date' => $lot['expiration_date'] ?? '1900-01-01',
                ];
            }
        }

        foreach (array_chunk($data, 500) as $chunk) {
            DB::table('product_lots')->insert($chunk);
        }

    }
}
