<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class AddLaboratoryRelationshipOnProducts extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $json = File::get(database_path('data/products.json'));
        $products = json_decode($json, true);

        $validLabsLookup = DB::table('laboratories')->pluck('id')->flip();

        $caseStatements = [];
        $productIds = [];

        foreach ($products as $product) {
            $productId = (int) $product['id'];
            $labId = isset($product['laboratory_id']) ? (int) $product['laboratory_id'] : null;

            $newLabId = ($labId !== null && isset($validLabsLookup[$labId]))
                ? $labId
                : 'NULL';

            $caseStatements[] = "WHEN {$productId} THEN {$newLabId}";
            $productIds[] = $productId;
        }

        if (empty($caseStatements)) {
            $this->command->info('No products to update.');
            return;
        }

        $caseSql = implode(' ', $caseStatements);
        $idsSql = implode(',', $productIds);

        DB::statement("
            UPDATE products 
            SET laboratory_id = CASE id {$caseSql} END
            WHERE id IN ({$idsSql})
        ");

    }
}
