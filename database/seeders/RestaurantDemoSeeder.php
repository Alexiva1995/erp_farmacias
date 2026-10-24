<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Category;
use App\Models\Product;
use App\Models\Lot;
use App\Models\Dish;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\KitchenStation;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\User;
use App\Models\Client;
use App\Enums\ModifierTypeEnum;
use App\Enums\KitchenStatusEnum;
use Carbon\Carbon;

class RestaurantDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            // 0. Estaciones de Cocina
            $stCaliente = KitchenStation::firstOrCreate(
                ['code' => 'HOT'],
                [
                    'name' => 'Cocina Caliente',
                    'color' => 'error',
                    'icon' => 'tabler-flame',
                    'sort_order' => 1,
                    'is_active' => true,
                ]
            );

            $stFria = KitchenStation::firstOrCreate(
                ['code' => 'COLD'],
                [
                    'name' => 'Cocina Fría / Ensaladas',
                    'color' => 'info',
                    'icon' => 'tabler-snowflake',
                    'sort_order' => 2,
                    'is_active' => true,
                ]
            );

            $stBar = KitchenStation::firstOrCreate(
                ['code' => 'BAR'],
                [
                    'name' => 'Bar & Coctelería',
                    'color' => 'primary',
                    'icon' => 'tabler-glass-full',
                    'sort_order' => 3,
                    'is_active' => true,
                ]
            );

            $stPostres = KitchenStation::firstOrCreate(
                ['code' => 'DESSERT'],
                [
                    'name' => 'Postres & Cafetería',
                    'color' => 'warning',
                    'icon' => 'tabler-cake',
                    'sort_order' => 4,
                    'is_active' => true,
                ]
            );

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

                if ($product->lots()->count() === 0) {
                    Lot::create([
                        'product_id' => $product->id,
                        'code' => 'LOTE-DEMO-' . strtoupper(substr(md5($product->barcode), 0, 6)),
                        'cost' => $product->unit_cost,
                        'current_stock' => $item['stock'],
                        'initial_stock' => $item['stock'],
                        'expiration_date' => Carbon::now()->addMonths(6)->toDateString(),
                        'state' => 'available',
                        'is_active' => true,
                    ]);
                }

                $insumosMap[$item['barcode']] = $product;
            }

            // 3. Modificadores
            $groupTermino = ModifierGroup::firstOrCreate(
                ['name' => 'Término de la Carne'],
                [
                    'description' => 'Punto de cocción preferido',
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

            // 4. Platos Terminados
            // Plato 1: Hamburguesa Doble Bacon (Cocina Caliente, Gravada con IVA)
            $dishBurger = Dish::firstOrCreate(
                ['name' => 'Hamburguesa Doble Bacon Cheddar'],
                [
                    'category_id' => $catBurgers->id,
                    'cost_price' => 2.80,
                    'cpv' => 2.80,
                    'suggested_price' => 5.04,
                    'designated_price' => 8.50,
                    'percentage_profit' => 1.8,
                    'status' => 1,
                    'iva' => true,
                    'tax_rate' => 16.00,
                    'preparation_time_minutes' => 18,
                    'kitchen_station_id' => $stCaliente->id,
                    'description' => 'Doble carne de 150g, queso cheddar fundido, tocineta crujiente y cebolla caramelizada en pan brioche.',
                ]
            );

            // Escandallo de la Hamburguesa (con is_removable en Cebolla)
            $dishBurger->ingredients()->sync([
                $insumosMap['INS-CARNE-01']->id => ['portion' => 0.30, 'designated_cost' => 1.35, 'is_removable' => false],
                $insumosMap['INS-PAN-01']->id => ['portion' => 1.00, 'designated_cost' => 0.40, 'is_removable' => false],
                $insumosMap['INS-CHEDDAR-01']->id => ['portion' => 0.05, 'designated_cost' => 0.25, 'is_removable' => true],
                $insumosMap['INS-BACON-01']->id => ['portion' => 0.06, 'designated_cost' => 0.39, 'is_removable' => true],
                $insumosMap['INS-CEBOLLA-01']->id => ['portion' => 0.04, 'designated_cost' => 0.05, 'is_removable' => true],
            ]);

            // Plato 2: Pizza Margarita Artesanal (Cocina Caliente, Exenta)
            $dishPizza = Dish::firstOrCreate(
                ['name' => 'Pizza Margarita Artesanal'],
                [
                    'category_id' => $catPizzas->id,
                    'cost_price' => 3.20,
                    'cpv' => 3.20,
                    'suggested_price' => 6.40,
                    'designated_price' => 10.00,
                    'percentage_profit' => 2.0,
                    'status' => 1,
                    'iva' => false,
                    'tax_rate' => 0.00,
                    'preparation_time_minutes' => 20,
                    'kitchen_station_id' => $stCaliente->id,
                    'description' => 'Masa artesanal fina, salsa pomodoro italiana y mozzarella rallado fundido.',
                ]
            );

            $dishPizza->ingredients()->sync([
                $insumosMap['INS-MASA-01']->id => ['portion' => 1.00, 'designated_cost' => 1.00, 'is_removable' => false],
                $insumosMap['INS-POMODORO-01']->id => ['portion' => 0.20, 'designated_cost' => 0.30, 'is_removable' => true],
                $insumosMap['INS-MOZZARELLA-01']->id => ['portion' => 0.35, 'designated_cost' => 1.90, 'is_removable' => false],
            ]);

            // 5. Comandas de Demostración para el KDS
            $client = Client::first();
            $seller = User::first();

            if ($client && $seller) {
                // Comanda 1: En Fuego / Preparando (Mesa 4)
                $order1 = Order::create([
                    'client_id' => $client->id,
                    'seller_id' => $seller->id,
                    'order_date' => Carbon::now()->subMinutes(8),
                    'status' => Order::PENDING,
                    'total_amount' => 17.00,
                    'total_amount_usd' => 17.00,
                    'currency' => 'USD',
                ]);

                OrderDetail::create([
                    'order_id' => $order1->id,
                    'dish_id' => $dishBurger->id,
                    'product_type' => 'dish',
                    'quantity' => 2,
                    'price' => 8.50,
                    'unit_price_usd' => 8.50,
                    'unit_cost' => 2.80,
                    'notes' => 'Término 3/4 - Sin Cebolla',
                    'kitchen_status' => KitchenStatusEnum::PREPARING->value,
                    'course_number' => 2,
                    'commanded_at' => Carbon::now()->subMinutes(8),
                ]);

                // Comanda 2: En Cola / Recién Marchada (Mesa 7)
                $order2 = Order::create([
                    'client_id' => $client->id,
                    'seller_id' => $seller->id,
                    'order_date' => Carbon::now()->subMinutes(2),
                    'status' => Order::PENDING,
                    'total_amount' => 18.50,
                    'total_amount_usd' => 18.50,
                    'currency' => 'USD',
                ]);

                OrderDetail::create([
                    'order_id' => $order2->id,
                    'dish_id' => $dishPizza->id,
                    'product_type' => 'dish',
                    'quantity' => 1,
                    'price' => 10.00,
                    'unit_price_usd' => 10.00,
                    'unit_cost' => 3.20,
                    'notes' => 'Bien dorada',
                    'kitchen_status' => KitchenStatusEnum::QUEUED->value,
                    'course_number' => 2,
                    'commanded_at' => Carbon::now()->subMinutes(2),
                ]);

                OrderDetail::create([
                    'order_id' => $order2->id,
                    'dish_id' => $dishBurger->id,
                    'product_type' => 'dish',
                    'quantity' => 1,
                    'price' => 8.50,
                    'unit_price_usd' => 8.50,
                    'unit_cost' => 2.80,
                    'notes' => 'Término Medio',
                    'kitchen_status' => KitchenStatusEnum::QUEUED->value,
                    'course_number' => 2,
                    'commanded_at' => Carbon::now()->subMinutes(2),
                ]);
            }
        });
    }
}
