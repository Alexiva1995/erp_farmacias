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
        $data = [];

        $categoryIds = DB::table('categories')->pluck('id')->toArray();

        if (empty($categoryIds)) {
            $this->call(CategoriesSeeder::class);
            $categoryIds = DB::table('categories')->pluck('id')->toArray();
        }
        $laboratories = DB::table('laboratories')->pluck('id')->flip();
        $origins = DB::table('origins')->pluck('id')->flip();

        // Recorrer y preparar los datos
        foreach ($products as &$product) {
            $data[] = [
                'id' => $product['id'],
                'category_id' => $categoryIds[array_rand($categoryIds)],
                'laboratory_id' => $laboratories->has($product['laboratory_id']) ? $product['laboratory_id'] : null,
                'origin_id' => $origins->has($product['provenance_id']) ? $product['provenance_id'] : null,
                'name' => $product['name'],
                'active_ingredient' => $product['active_ingredient'] ?? null,
                'unit_cost' => $product['cost'] ?? 0.0,
                'sale_price' => $product['price'] ?? 0.0,
                'is_colombian_origin' => $product['is_colombian'] ?? 0,
                'barcode' => $product['code_bar'] ?? null,
                'iva' => $product['has_tax'],
                'stock' => $product['units'],
                'psychotropic' => 0,
                'photo_url' => null,
                'sales_average' => $products['average_sales'] ?? 0.0,
                'created_at' => $product['created_at'],
                'updated_at' => $product['updated_at'],
            ];
        }

        // Insertar en chunks
        foreach (array_chunk($data, 500) as $chunk) {
            DB::table('products')->insert($chunk);
        }
    }
}
