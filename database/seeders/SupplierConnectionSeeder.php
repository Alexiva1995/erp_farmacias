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
        $json = File::get(database_path("data/supplier_connections.json"));
        $supplier_connections = json_decode($json, true);

        $prepared = array_map(
            fn(array $item) => [
                ...$item,
                "structure" => json_encode($item["structure"]),
                "invoice_structure" => isset($item["invoice_structure"]) ? json_encode($item["invoice_structure"]) : null,
            ],
            $supplier_connections,
        );

        // Insertar en chunks
        foreach (array_chunk($prepared, 500) as $chunk) {
            DB::table("supplier_connections")->insert($chunk);
        }
    }
}
