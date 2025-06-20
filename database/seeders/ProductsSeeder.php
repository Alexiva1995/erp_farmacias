<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Product;

class ProductsSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Product::truncate();

        $products_data = [
            [5946, 'ACICLOVIR 200mg X 10 TAB BLISTER', 'ACICLOVIR', 1, 1, NULL, 1.19, 1.48, 0, 0, 0, '7750215003602', NULL, NULL, '2022-09-06 08:37:58', '2025-06-03 03:26:31'],
            [5948, 'ACIDO BORICO X 20 GR SOBRE', 'ACIDO BORICO', 4, 2, NULL, 1.30, 1.63, 0, 0, 0, '7591616001466', NULL, NULL, '2022-09-06 08:37:58', '2025-06-12 22:14:22'],
            [5957, 'ALBENDAZOL 200MG X 6 TAB', 'ALBENDAZOL', 5, 12, NULL, 1.30, 1.62, 0, 0, 0, '7594001100256', NULL, NULL, '2022-09-06 08:37:58', '2025-06-03 03:26:31'],
            [5965, 'ANTAAR 2.5MG X 30 TAB', 'BISOPROLOL FUMARATO', 7, 2, NULL, 3.14, 3.93, 0, 0, 0, '7591519051872', NULL, NULL, '2022-09-06 08:37:58', '2025-06-03 03:26:31'],
            [5966, 'ANTAAR HCT 2.5 MG-6.25 MG X 10 TAB', 'BISOPROLOL-HIDROCLOROTIAZIDA', 7, 2, NULL, 2.83, 3.53, 0, 0, 0, '7591519317749', NULL, NULL, '2022-09-06 08:37:58', '2025-06-03 03:26:31'],
            [5967, 'ANTAAR HCT 5 MG-6.25 MG X 10 TAB', 'BISOPROLOL+HIDROCLOROTIAZIDA', 7, 2, NULL, 2.65, 3.31, 0, 0, 0, '7591519317756', NULL, NULL, '2022-09-06 08:37:58', '2025-06-03 03:26:31'],
            [5971, 'ARUDIL 15MG X 30 TAB REC', 'RIVAROXABAN', NULL, NULL, NULL, 19.41, 24.27, 0, 0, 0, 'NULL_BARCODE_ROW_5971', NULL, NULL, '2022-09-06 08:37:58', '2025-06-03 03:26:31'],
            [5972, 'ARUDIL 20MG X 30 TAB REC', 'RIVAROXABAN', 8, 2, NULL, 22.11, 27.64, 0, 0, 0, '7591821210578', NULL, NULL, '2022-09-06 08:37:58', '2025-06-03 03:26:31'],
            [5976, 'AZUTAN 0.15% COLUTORIO TOPICO BUCAL X 180ML', 'CLORHIDRATO DE BENZIDAMINA', 93, 2, NULL, 4.91, 6.14, 0, 0, 0, '7591243803167', NULL, NULL, '2022-09-06 08:37:58', '2025-06-03 03:26:31'],
            [5980, 'BEPROSPEN 7MG X 1 AMP +INY', 'DIPROPIONATO DE BETAMETASONA-FOSFATO DE BETAMETASONA', 93, NULL, NULL, 4.60, 5.75, 0, 0, 0, 'NULL_BARCODE_ROW_5980', NULL, NULL, '2022-09-06 08:37:58', '2025-06-11 17:32:20'],
            [5981, 'BETADERM 0.05% X 15 GR CREMA', 'BETAMETASONA VALERATO', 93, 2, NULL, 4.69, 5.87, 0, 0, 0, '7591243804140', NULL, NULL, '2022-09-06 08:37:58', '2025-06-11 23:59:51'],
            [5982, 'BETADERM CON GENTAMICINA 0.1%+0.1 X 15 GR CREMA', 'BETAMETASONA+GENTAMICINA', 93, 2, NULL, 5.10, 6.37, 0, 0, 0, '7591243804041', NULL, NULL, '2022-09-06 08:37:58', '2025-06-03 03:26:31'],
            [5987, 'BEXILON 4MG/5ML X 120 ML', 'CLORHIDRATO DE BROMEXINA', 5, 2, NULL, 1.48, 1.85, 0, 0, 0, '7594001100065', NULL, NULL, '2022-09-06 08:37:58', '2025-06-12 09:00:10'],
            [5992, 'BIOFENIT 250MG/5ML AMP I.V/I.M', 'FENITOINA SODICA', 52, 5, NULL, 6.44, 8.05, 0, 0, 0, '7598455000209', NULL, NULL, '2022-09-06 08:37:58', '2025-06-03 03:26:31'],
            [5996, 'BIOVIT C 500 MG/ 5ML X 1 AMP I.V/I.M', 'ACIDO ASCORBICO-VITAMINA C', 52, 4, NULL, 0.80, 1.01, 0, 0, 0, 'NULL_BARCODE_ROW_5996', NULL, NULL, '2022-09-06 08:37:58', '2025-06-03 03:26:31'],
            [6001, 'BLOCAX 8MG X 10 TAB', 'CANDESARTAN CILEXETILO', 93, 2, NULL, 2.32, 2.91, 0, 0, 0, '7591243805321', NULL, NULL, '2022-09-06 08:37:58', '2025-06-03 03:26:31'],
            [6003, 'BRAL 500MG X 20 TAB', 'DIPIRONA', 93, 2, NULL, 4.34, 5.42, 0, 0, 0, '7591243807202', NULL, NULL, '2022-09-06 08:37:58', '2025-06-11 23:00:50'],
            [6025, 'CLIMASOY 100MG X 30 CAP', 'ISOFLAVONAS DE SOYA', 8, 2, NULL, 10.92, 13.65, 0, 0, 0, '7591821802735', NULL, NULL, '2022-09-06 08:37:58', '2025-06-03 03:26:31'],
            [6033, 'CLORURO DE MAGNESIO AL 3.33% X 1000ML', 'CLORURO DE MAGNESIO', 4, 2, NULL, 6.31, 7.89, 0, 0, 0, '7591616002944', NULL, NULL, '2022-09-06 08:37:58', '2025-06-03 03:26:31'],
            [6034, 'COLAYTE X 1 SOBRE 69.57 GR', 'POLIETILENGLICOL', 87, 2, NULL, 5.48, 6.85, 0, 0, 0, '7592806132076', NULL, NULL, '2022-09-06 08:37:58', '2025-06-03 03:26:31'],
        ];

        $products_to_insert = array_map(function ($product) {
            return [
                'id' => $product[0],
                'name' => $product[1],
                'active_ingredient' => $product[2],
                'laboratory_id' => $product[3],
                'origin_id' => $product[4],
                'category_id' => $product[5],
                'cost_price' => $product[6],
                'sale_price' => $product[7],
                'iva' => $product[8],
                'from_colombia' => $product[9],
                'psychotropic' => $product[10],
                'barcode' => $product[11],
                'photo_url' => $product[12],
                'deleted_at' => $product[13],
                'created_at' => $product[14],
                'updated_at' => $product[15],
            ];
        }, $products_data);

        Product::insert($products_to_insert);
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}
