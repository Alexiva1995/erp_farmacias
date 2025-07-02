<?php

namespace Database\Seeders;

use App\Models\FiscalHistory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class FiscalHistorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        for ($i = 0; $i < 5; $i++) {
            FiscalHistory::create([
                'user_id'         => rand(2, 3), // aleatoriamente 2 o 3
                'fiscal_id'       => null,
                'invoice_number'  => Str::upper(Str::random(10)),
                'business_name'   => 'Empresa Ejemplo ' . $i,
                'identification'  => 'NIT' . rand(10000000, 99999999),
                'address'         => 'Calle Falsa 123',
                'exempt_amount'   => rand(10000, 50000),
                'iva_amount'      => rand(1000, 9000),
                'total_amount'    => rand(60000, 100000),
                'invoice_date'    => Carbon::now()->subDays(rand(1, 365)),
            ]);
        }
    }
}
