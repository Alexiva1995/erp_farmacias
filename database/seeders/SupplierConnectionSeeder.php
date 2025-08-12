<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class SupplierConnectionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $json = File::get(database_path('data/supplier_connections.json'));
        $supplier_connections = json_decode($json, true);

        // Insertar en chunks
        foreach (array_chunk($supplier_connections, 500) as $chunk) {
            DB::table('supplier_connections')->insert($chunk);
        }
    }
}
