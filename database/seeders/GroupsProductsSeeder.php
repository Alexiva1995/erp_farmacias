<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;

class GroupsProductsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = json_decode(File::get(database_path('data/alternativa_product.json')), true);
        $adj = [];
        $allProducts = [];
        $this->command->info("Iniciando la carga de " . count($data) . " registros...");
        foreach ($data as $row) {
            $u = $row['product_id'];
            $v = $row['related_product_id'];
            $adj[$u][] = $v;
            $adj[$v][] = $u;
            $allProducts[$u] = true;
            $allProducts[$v] = true;
        }
        $visited = [];
        $groups = [];

        foreach (array_keys($allProducts) as $productId) {
            if (!isset($visited[$productId])) {
                $currentGroup = [];
                $stack = [$productId];
                $visited[$productId] = true;

                while (!empty($stack)) {
                    $curr = array_pop($stack);
                    $currentGroup[] = $curr;

                    foreach ($adj[$curr] ?? [] as $neighbor) {
                        if (!isset($visited[$neighbor])) {
                            $visited[$neighbor] = true;
                            $stack[] = $neighbor;
                        }
                    }
                }
                $groups[] = $currentGroup;
            }
        }
        $this->command->info("Se detectaron " . count($groups) . " grupos de productos.");
        foreach ($groups as $index => $productIds) {
            $groupId = DB::table('groups_products')->insertGetId([
                'name' => 'Grupo Relacionado #' . ($index + 1),
                'created_at' => now(),
                'updated_at' => now()
            ]);
            DB::table('products')
                ->whereIn('id', $productIds)
                ->update(['group_id' => $groupId]);
        }
        $this->command->info("¡Seeder ejecutado con éxito!");
    }
}
