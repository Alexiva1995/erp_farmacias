<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class SuppliersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $json = File::get(database_path('data/suppliers.json'));
        $suppliers = json_decode($json, true);

        foreach ($suppliers as &$supplier) {
            $supplier['supplier_name'] = $supplier['name'];
            $supplier['sales_phone'] = $supplier['advisor_phone_number'] ?? null;
            $supplier['collections_phone'] = $supplier['advisor_phone_number_2'] ?? null;
            $supplier['payment_method'] = $supplier['money'] === 'usd' ? 'Divisas' : 'Bs';
            $supplier['charges_igtf'] = $supplier['igtf'] ?? 0;
            $supplier['cash_payment'] = $supplier['cash_payment'] ?? 0;

            unset($supplier['name'], $supplier['advisor_phone_number'], $supplier['advisor_phone_number_2'], $supplier['money'], $supplier['igtf']);
        }

        // Insertar en chunks
        foreach (array_chunk($suppliers, 500) as $chunk) {
            DB::table('suppliers')->insert($chunk);
        }
    }
}
