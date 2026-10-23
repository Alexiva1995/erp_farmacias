<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ModifierTypeEnum;
use App\Models\Category;
use App\Models\Dish;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Product;
use App\Models\ProductLot;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RestaurantDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            // 1. Categorías gastronómicas
            $catBurgers = Category::firstOrCreate(['name' => 'Hamburguesas Gourmet']);
            $catPizzas = Category::firstOrCreate(['name' => 'Pizzas Artesanales']);
            $catBebidas = Category::firstOrCreate(['name' => 'Bebidas & Coctelería']);

            // 2. Insumos con Lotes de Stock
            $insumosData = [
                ['name' => 'Carne de Res Molida Premium (kg)', 'barcode' => 'INS-CARNE-01', 'unit_cost' => 4.50, 'sale_price' => 6.00, 'unit_of_measure' => 'kg', 'stock' => 50],
                ['name' => 'Pan Brioche para Hamburguesa (und)', 'barcode' => 'INS-PAN-01', 'unit_cost' => 0.40, 'sale_price' => 0.60, 'unit_of_measure' => 'und', 'stock' => 120],
                ['name' => 'Queso Cheddar Americano (kg)', 'barcode' => 'INS-CHEDDAR-01', 'unit_cost' => 5.00, 'sale_price' => 7.00, 'unit_of_measure' => 'kg', 'stock' => 30],
                ['name' => 'Tocineta Ahumada Crispy (kg)', 'barcode' => 'INS-BACON-01', 'unit_cost' => 6.50, 'sale_price' => 9.00, 'unit_of_measure' => 'kg', 'stock' => 25],
                ['name' => 'Cebolla Morada Caramelizada (kg)', 'barcode' => 'INS-CEBOLLA-01', 'unit_cost' => 1.20, 'sale_price' => 2.00, 'unit_of_measure' => 'kg', 'stock' => 40],
                ['name' => 'Masa de Pizza Italiana (und)', 'barcode' => 'INS-MASA-01', 'unit_cost' => 1.00, 'sale_price' => 1.80, 'unit_of_measure' => 'und', 'stock' => 60],
                ['name' => 'Queso Mozzarella Rallado (kg)', 'barcode' => 'INS-MOZZARELLA-01', 'unit_cost' => 5.50, 'sale_price' => 7.50, 'unit_of_measure' => 'kg', 'stock' => 35],
                ['name' => 'Salsa Pomodoro Casera (kg)', 'barcode' => 'INS-POMODORO-01', 'unit_cost' => 1.50, 'sale_price' => 2.50, 'unit_of_measure' => 'kg', 'stock' => 40],
            ];

            $insumosMap = [];
            foreach ($insumosData as $item) {
                $product = Product::firstOrCreate(
                    ['barcode' => $item['barcode']],
                    [
                        'name' => $item['name'],
                        'unit_cost' => $item['unit_cost'],
                        'sale_price' => $item['sale_price'],
                        'presentation' => 1,
                        'unit_of_measure' => $item['unit_of_measure'],
                        'iva' => 0,
                        'stock' => $item['stock'],
                        'is_active' => true,
                    ]
                );

                // Crear lote activo si no existe
                if ($product->lots()->count() === 0) {
                    ProductLot::create([
                        'product_id' => $product->id,
                        'lot_number' => 'LOT-' . strtoupper(substr(md5($item['barcode']), 0, 8)),
                        'quantity' => $item['stock'],
                        'unit_cost' => $item['unit_cost'],
                        'expiration_date' => Carbon::now()->addMonths(6)->toDateString(),
                    ]);
                }

                $insumosMap[$item['barcode']] = $product;
            }

            // 3. Grupos de Modificadores
            // Grupo A: Término de la Carne (Opciones informativas de cocina)
            $groupTermino = ModifierGroup::firstOrCreate(
                ['name' => 'Término de la Carne'],
                [
                    'description' => 'Punto de cocción solicitado para la carne',
                    'min_selections' => 1,
                    'max_selections' => 1,
                    'is_active' => true,
                ]
            );

            ModifierOption::firstOrCreate(['modifier_group_id' => $groupTermino->id, 'name' => 'Término Medio (Jugoso)'], [
                'modifier_type' => ModifierTypeEnum::INFO,
                'extra_price_usd' => 0.00,
                'sort_order' => 1,
                'is_active' => true,
            ]);

            ModifierOption::firstOrCreate(['modifier_group_id' => $groupTermino->id, 'name' => 'Tres Cuartos (Recomendado)'], [
                'modifier_type' => ModifierTypeEnum::INFO,
                'extra_price_usd' => 0.00,
                'sort_order' => 2,
                'is_active' => true,
            ]);

            ModifierOption::firstOrCreate(['modifier_group_id' => $groupTermino->id, 'name' => 'Bien Cocido'], [
                'modifier_type' => ModifierTypeEnum::INFO,
                'extra_price_usd' => 0.00,
                'sort_order' => 3,
                'is_active' => true,
            ]);

            // Grupo B: Adiciones / Extras con Precio y Descarga de Lote
            $groupExtras = ModifierGroup::firstOrCreate(
                ['name' => 'Adiciones & Extras'],
                [
                    'description' => 'Ingredientes adicionales para personalizar tu plato',
                    'min_selections' => 0,
                    'max_selections' => 5,
                    'is_active' => true,
                ]
            );

            ModifierOption::firstOrCreate(['modifier_group_id' => $groupExtras->id, 'name' => 'Extra Tocineta Ahumada'], [
                'modifier_type' => ModifierTypeEnum::ADD,
                'extra_price_usd' => 1.50,
                'product_id' => $insumosMap['INS-BACON-01']->id,
                'portion' => 0.08, // 80 gramos de tocineta
                'sort_order' => 1,
                'is_active' => true,
            ]);

            ModifierOption::firstOrCreate(['modifier_group_id' => $groupExtras->id, 'name' => 'Extra Queso Cheddar'], [
                'modifier_type' => ModifierTypeEnum::ADD,
                'extra_price_usd' => 1.00,
                'product_id' => $insumosMap['INS-CHEDDAR-01']->id,
                'portion' => 0.05, // 50 gramos de cheddar
                'sort_order' => 2,
                'is_active' => true,
            ]);

            // Grupo C: Omisiones (Sin Cebolla / Sin Salsa)
            $groupOmisiones = ModifierGroup::firstOrCreate(
                ['name' => 'Preferencias / Omitir Ingredientes'],
                [
                    'description' => 'Exclusiones de insumos sin recargo',
                    'min_selections' => 0,
                    'max_selections' => 5,
                    'is_active' => true,
                ]
            );

            ModifierOption::firstOrCreate(['modifier_group_id' => $groupOmisiones->id, 'name' => 'Sin Cebolla'], [
                'modifier_type' => ModifierTypeEnum::REMOVE,
                'extra_price_usd' => 0.00,
                'product_id' => $insumosMap['INS-CEBOLLA-01']->id,
                'portion' => 0, // 0 = omite la descarga del ingrediente de la receta
                'sort_order' => 1,
                'is_active' => true,
            ]);

            // 4. Platos Terminados
            // Plato 1: Hamburguesa Doble Bacon BBQ (Gravada con IVA 16%)
            $dishBurger = Dish::firstOrCreate(
                ['name' => 'Hamburguesa Doble Bacon Cheddar'],
                [
                    'category_id' => $catBurgers->id,
                    'cost_price' => 2.80,
                    'cpv' => 2.80,
                    'suggested_price' => 7.50,
                    'designated_price' => 8.50,
                    'percentage_profit' => 1.8,
                    'status' => 1,
                    'iva' => true, // Gravada con IVA
                    'tax_rate' => 16.00,
                    'preparation_time_minutes' => 18,
                    'description' => 'Doble carne de 150g, queso cheddar fundido, tocineta crujiente y cebolla caramelizada en pan brioche.',
                ]
            );

            // Escandallo / Receta de la Hamburguesa
            $dishBurger->ingredients()->syncWithoutDetaching([
                $insumosMap['INS-CARNE-01']->id => ['portion' => 0.30, 'designated_cost' => 1.35],
                $insumosMap['INS-PAN-01']->id => ['portion' => 1.00, 'designated_cost' => 0.40],
                $insumosMap['INS-CHEDDAR-01']->id => ['portion' => 0.05, 'designated_cost' => 0.25],
                $insumosMap['INS-BACON-01']->id => ['portion' => 0.06, 'designated_cost' => 0.39],
                $insumosMap['INS-CEBOLLA-01']->id => ['portion' => 0.04, 'designated_cost' => 0.05],
            ]);

            // Asignar los 3 grupos de modificadores a la hamburguesa
            $dishBurger->modifierGroups()->syncWithoutDetaching([
                $groupTermino->id => ['sort_order' => 1],
                $groupExtras->id => ['sort_order' => 2],
                $groupOmisiones->id => ['sort_order' => 3],
            ]);

            // Plato 2: Pizza Margarita Familiar (Exenta)
            $dishPizza = Dish::firstOrCreate(
                ['name' => 'Pizza Margarita Artesanal'],
                [
                    'category_id' => $catPizzas->id,
                    'cost_price' => 3.20,
                    'cpv' => 3.20,
                    'suggested_price' => 9.00,
                    'designated_price' => 10.00,
                    'percentage_profit' => 2.0,
                    'status' => 1,
                    'iva' => false, // Exenta
                    'tax_rate' => 0.00,
                    'preparation_time_minutes' => 25,
                    'description' => 'Masa artesanal fina, salsa pomodoro italiana, abundante mozzarella y albahaca fresca.',
                ]
            );

            $dishPizza->ingredients()->syncWithoutDetaching([
                $insumosMap['INS-MASA-01']->id => ['portion' => 1.00, 'designated_cost' => 1.00],
                $insumosMap['INS-POMODORO-01']->id => ['portion' => 0.20, 'designated_cost' => 0.30],
                $insumosMap['INS-MOZZARELLA-01']->id => ['portion' => 0.35, 'designated_cost' => 1.90],
            ]);

            $dishPizza->modifierGroups()->syncWithoutDetaching([
                $groupExtras->id => ['sort_order' => 1],
            ]);
        });
    }
}
