<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
class MigrarDailyClosuresSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $path = database_path('data/MigrarDailyClosures.json');

        if (!File::exists($path)) {
            $this->command->error("No se encontró el archivo en: $path");
            return;
        }
        
        $jsonData = json_decode(File::get($path), true);
        $dataToInsert = [];
        $now = Carbon::now();
        foreach ($jsonData as $item) {
            $dataToInsert[] = [
                'id'                   => $item['id'],
                'total_sales'          => $item['total_amount'] ?? 0, 
                'total_usd'            => $item['amount_usd'] ?? 0, 
                'total_cop'            => $item['amount_cop'] ?? 0, 
                'total_bs'             => $item['amount_bs'] ?? 0, 
                'bs_card'              => 0.00,
                'bs_mobile'            => 0.00,
                'usd_delivered'        => $item['entrega_usd'] ?? 0,
                'cop_delivered'        => $item['entrega_cop'] ?? 0,
                'bs_delivered'         => 0.00,
                'total_credits'        => 0.00,
                'total_payment_credit' => 0.00,
                'total_delivery'       => 0.00,
                'created_at'           => isset($item['created_at']) ? Carbon::parse($item['created_at']) : $now,
                'updated_at'           => $now,
            ];
        }

        DB::transaction(function () use ($dataToInsert) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            foreach (array_chunk($dataToInsert, 100) as $chunk) {
                DB::table('daily_closures')->insert($chunk);
            }
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        });
        $this->command->info("¡Seeder ejecutado con éxito! Se migraron " . count($dataToInsert) . " registros.");
    }
}
