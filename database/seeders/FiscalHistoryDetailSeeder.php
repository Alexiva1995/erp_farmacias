<?php

namespace Database\Seeders;

use App\Models\FiscalHistoryDetail;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FiscalHistoryDetailSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $productDetails = [
            ['id' => 5946, 'name' => 'ACICLOVIR 200mg X 10 TAB'],
            ['id' => 5948, 'name' => 'ACIDO BORICO X 20 GR SOBRE'],
            ['id' => 5957, 'name' => 'ALBENDAZOL 200MG X 6 TAB'],
        ];

        foreach (range(1, 3) as $fiscalHistoryId) {
            foreach ($productDetails as $product) {
                FiscalHistoryDetail::create([
                    'fiscal_history_id' => $fiscalHistoryId,
                    'product_id'        => $product['id'],
                    'product_name'      => $product['name'],
                    'quantity'          => rand(1, 5),
                    'vat_status'        => 0,
                    'exempt_amount'     => rand(1000, 3000),
                    'iva_amount'        => rand(500, 1500),
                    'total_amount'      => rand(2000, 5000),
                ]);
            }
        }
    }
}
