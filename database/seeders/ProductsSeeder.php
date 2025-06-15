<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class ProductsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $json = File::get(database_path('data/products.json'));
        $products = json_decode($json, true);

        $categoryIds = DB::table('categories')->pluck('id')->toArray();
        $groupIds = DB::table('groups_products')->pluck('id')->toArray();
        $missingCount = 0;

        // Recorrer y preparar los datos
        foreach ($products as &$product) {
            $missingCount++;

            $product['category_id'] = $categoryIds[array_rand($categoryIds)];
            $product['group_id'] = $groupIds[array_rand($groupIds)];
            $product['origin_id'] = $product['provenance_id'];
            $product['cost_price'] = $product['cost'];
            $product['sale_price'] = $product['price'] ?? 0;
            $product['stock'] = $product['units'] ?? 0;
            $product['from_colombia'] = $product['is_colombian'] ?? 0;
            $product['active_ingredient'] = $product['active_ingredient'] ?? 'MISSING';
            $product['barcode'] = $product['code_bar'] ?? 'MISSING-' . str_pad($missingCount, 6, '0', STR_PAD_LEFT);
            $product['iva'] = 0;
            $product['psychotropic'] = 0;
            $product['photo_url'] = null;

            unset($product['cost'], $product['price'], $product['units'], $product['is_colombian'], $product['code_bar'], $product['provenance_id']);
        }

        // Insertar en chunks
        foreach (array_chunk($products, 500) as $chunk) {
            DB::table('products')->insert($chunk);
        }
    }
}
