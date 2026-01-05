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
            $dispatch_days = [];
            foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'] as $day) {
                if (!empty($supplier[$day])) {
                    $dispatch_days[] = $day;
                }
            }

            $supplier['sales_phone'] = $supplier['advisor_phone_number'] ?? null;
            $supplier['collections_phone'] = $supplier['advisor_phone_number_2'] ?? null;
            $supplier['payment_method'] = $supplier['money'] === 'usd' ? 'Divisas' : 'Bs';
            $supplier['charges_igtf'] = $supplier['igtf'] ?? 0;
            $supplier['cash_payment'] = $supplier['cash_payment'] ?? 0;
            $supplier['dispatch_days'] = json_encode($dispatch_days);
            $supplier['order_days'] = json_encode([]);

            unset(
                $supplier['advisor_phone_number'],
                $supplier['advisor_phone_number_2'],
                $supplier['sales_advisor'],
                $supplier['credit_days'],
                $supplier['method_payment'],
                $supplier['amount_minimum'],
                $supplier['logo'],
                $supplier['money'],
                $supplier['igtf'],
                $supplier['monday'],
                $supplier['tuesday'],
                $supplier['wednesday'],
                $supplier['thursday'],
                $supplier['friday'],
                $supplier['saturday'],
                $supplier['expired_date_invoice']
            );
        }

        // Insertar en chunks
        foreach (array_chunk($suppliers, 500) as $chunk) {
            DB::table('suppliers')->insert($chunk);
        }
    }
}
