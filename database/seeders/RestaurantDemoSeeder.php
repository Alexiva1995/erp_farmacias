<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\Dish;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\KitchenStation;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\User;
use App\Models\Client;
use App\Models\DiningZone;
use App\Models\DiningTable;
use App\Models\ProductionOrder;
use App\Models\KitchenWaste;
use App\Models\Reservation;
use App\Enums\ModifierTypeEnum;
use App\Enums\KitchenStatusEnum;
use App\Enums\DiningTableStatusEnum;
use App\Enums\TableShapeEnum;
use App\Enums\ProductionOrderStatusEnum;
use App\Enums\KitchenWasteReasonEnum;
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

            // 5. Zonas y Mesas de Salón (Fase 3) - Se crean siempre independientemente de clientes u órdenes previas
            $zonePrincipal = DiningZone::firstOrCreate(
                ['name' => 'Salón Principal'],
                [
                    'background_style' => 'grid',
                    'sort_order' => 1,
                    'is_active' => true,
                ]
            );

            $zoneTerraza = DiningZone::firstOrCreate(
                ['name' => 'Terraza VIP'],
                [
                    'background_style' => 'grass',
                    'sort_order' => 2,
                    'is_active' => true,
                ]
            );

            $table1 = DiningTable::firstOrCreate(
                ['name' => 'Mesa 1', 'dining_zone_id' => $zonePrincipal->id],
                [
                    'capacity' => 4,
                    'shape' => TableShapeEnum::SQUARE->value,
                    'pos_x' => 120,
                    'pos_y' => 120,
                    'width' => 90,
                    'height' => 90,
                    'rotation' => 0,
                    'status' => DiningTableStatusEnum::AVAILABLE->value,
                ]
            );

            $table2 = DiningTable::firstOrCreate(
                ['name' => 'Mesa 2', 'dining_zone_id' => $zonePrincipal->id],
                [
                    'capacity' => 6,
                    'shape' => TableShapeEnum::RECTANGLE->value,
                    'pos_x' => 280,
                    'pos_y' => 120,
                    'width' => 140,
                    'height' => 90,
                    'rotation' => 0,
                    'status' => DiningTableStatusEnum::AVAILABLE->value,
                ]
            );

            DiningTable::firstOrCreate(
                ['name' => 'Mesa 3', 'dining_zone_id' => $zonePrincipal->id],
                [
                    'capacity' => 2,
                    'shape' => TableShapeEnum::ROUND->value,
                    'pos_x' => 480,
                    'pos_y' => 120,
                    'width' => 80,
                    'height' => 80,
                    'rotation' => 0,
                    'status' => DiningTableStatusEnum::AVAILABLE->value,
                ]
            );

            DiningTable::firstOrCreate(
                ['name' => 'Barra 1', 'dining_zone_id' => $zonePrincipal->id],
                [
                    'capacity' => 1,
                    'shape' => TableShapeEnum::BAR->value,
                    'pos_x' => 120,
                    'pos_y' => 280,
                    'width' => 60,
                    'height' => 60,
                    'rotation' => 0,
                    'status' => DiningTableStatusEnum::AVAILABLE->value,
                ]
            );

            DiningTable::firstOrCreate(
                ['name' => 'Barra 2', 'dining_zone_id' => $zonePrincipal->id],
                [
                    'capacity' => 1,
                    'shape' => TableShapeEnum::BAR->value,
                    'pos_x' => 200,
                    'pos_y' => 280,
                    'width' => 60,
                    'height' => 60,
                    'rotation' => 0,
                    'status' => DiningTableStatusEnum::AVAILABLE->value,
                ]
            );

            // Mesas en Terraza VIP
            DiningTable::firstOrCreate(
                ['name' => 'Terraza 1', 'dining_zone_id' => $zoneTerraza->id],
                [
                    'capacity' => 4,
                    'shape' => TableShapeEnum::ROUND->value,
                    'pos_x' => 150,
                    'pos_y' => 150,
                    'width' => 90,
                    'height' => 90,
                    'rotation' => 0,
                    'status' => DiningTableStatusEnum::AVAILABLE->value,
                ]
            );

            DiningTable::firstOrCreate(
                ['name' => 'Terraza Lounge', 'dining_zone_id' => $zoneTerraza->id],
                [
                    'capacity' => 8,
                    'shape' => TableShapeEnum::RECTANGLE->value,
                    'pos_x' => 320,
                    'pos_y' => 150,
                    'width' => 160,
                    'height' => 90,
                    'rotation' => 0,
                    'status' => DiningTableStatusEnum::RESERVED->value,
                ]
            );

            // 6. Comandas de Demostración para el KDS y Mesas
            $client = Client::first();
            $seller = User::first();

            if ($client && $seller) {
                // Comanda 1: En Fuego / Preparando vinculada a Mesa 1
                $order1 = Order::create([
                    'client_id' => $client->id,
                    'seller_id' => $seller->id,
                    'order_date' => Carbon::now()->subMinutes(8),
                    'status' => Order::PENDING,
                    'total_amount' => 17.00,
                    'total_amount_usd' => 17.00,
                    'total_cost' => 5.60,
                    'money_returns' => 0,
                    'usd_conversion' => 1,
                    'currency' => 'USD',
                    'dining_table_id' => $table1->id,
                    'guests_count' => 3,
                ]);

                $table1->update([
                    'status' => DiningTableStatusEnum::OCCUPIED->value,
                    'current_order_id' => $order1->id,
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
                    'seat_number' => 1,
                    'commanded_at' => Carbon::now()->subMinutes(8),
                ]);

                // Comanda 2: Con Pre-Cuenta Pedida vinculada a Mesa 2
                $order2 = Order::create([
                    'client_id' => $client->id,
                    'seller_id' => $seller->id,
                    'order_date' => Carbon::now()->subMinutes(2),
                    'status' => Order::PENDING,
                    'total_amount' => 18.50,
                    'total_amount_usd' => 18.50,
                    'total_cost' => 6.00,
                    'money_returns' => 0,
                    'usd_conversion' => 1,
                    'currency' => 'USD',
                    'dining_table_id' => $table2->id,
                    'guests_count' => 2,
                    'service_charge_percentage' => 10.00,
                    'service_charge_amount' => 1.85,
                    'service_charge_amount_usd' => 1.85,
                    'pre_bill_printed_at' => Carbon::now()->subMinutes(1),
                ]);

                $table2->update([
                    'status' => DiningTableStatusEnum::BILL_REQUESTED->value,
                    'current_order_id' => $order2->id,
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
                    'seat_number' => 1,
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
                    'seat_number' => 2,
                    'commanded_at' => Carbon::now()->subMinutes(2),
                ]);

                // 8. Fase 4: Sub-receta de Demostración y Orden de Producción
                $prodPomodoro = Product::firstOrCreate(
                    ['barcode' => 'SUB-POMODORO-01'],
                    [
                        'name' => 'Salsa Pomodoro Casera (Litros)',
                        'unit_cost' => 1.50,
                        'sale_price' => 2.50,
                        'yield_percentage' => 95.0,
                        'is_sub_recipe' => true,
                        'unit_of_measure' => 'L',
                        'stock' => 10.0,
                        'is_active' => true,
                        'category_id' => $catInsumos->id,
                    ]
                );

                $dishPomodoroReceta = Dish::firstOrCreate(
                    ['name' => 'Base Salsa Pomodoro 5L'],
                    [
                        'status' => 3, // Sub-receta
                        'output_product_id' => $prodPomodoro->id,
                        'expected_yield_quantity' => 5.0,
                        'shelf_life_days' => 14,
                        'kitchen_station_id' => $stCaliente->id,
                        'description' => 'Sub-receta de salsa madre italiana para pastas y pizzas.',
                    ]
                );

                $lotPomodoro = ProductLot::firstOrCreate(
                    ['lot_number' => 'LOT-POM-001'],
                    [
                        'product_id' => $prodPomodoro->id,
                        'quantity' => 5.0,
                        'unit_cost' => 1.45,
                        'expiration_date' => Carbon::now()->addDays(14)->toDateString(),
                    ]
                );

                ProductionOrder::firstOrCreate(
                    ['code' => 'PROD-DEMO-01'],
                    [
                        'dish_id' => $dishPomodoroReceta->id,
                        'output_product_id' => $prodPomodoro->id,
                        'user_id' => $seller->id,
                        'kitchen_station_id' => $stCaliente->id,
                        'batch_multiplier' => 1.0,
                        'expected_quantity' => 5.0,
                        'actual_quantity' => 5.0,
                        'total_production_cost' => 7.25,
                        'unit_cost_calculated' => 1.45,
                        'generated_lot_id' => $lotPomodoro->id,
                        'status' => ProductionOrderStatusEnum::COMPLETED,
                        'produced_at' => Carbon::now()->subHours(3),
                    ]
                );

                KitchenWaste::firstOrCreate(
                    ['notes' => 'Merma por limpieza y descarte de tallos de tomate'],
                    [
                        'user_id' => $seller->id,
                        'kitchen_station_id' => $stFria->id,
                        'item_type' => 'product',
                        'product_id' => $insumosMap['INS-TOMATE-01']->id,
                        'quantity' => 0.40,
                        'unit_cost' => 1.20,
                        'total_loss_usd' => 0.48,
                        'reason' => KitchenWasteReasonEnum::PREPARATION_TRIM,
                        'occurred_at' => Carbon::now()->subHours(2),
                    ]
                );

                // 9. Fase 5: Tokens QR en Mesas y Reservas Gastronómicas
                $table1->update(['qr_token' => 'qr_demo_mesa_1']);
                $table2->update(['qr_token' => 'qr_demo_mesa_2']);

                Reservation::firstOrCreate(
                    ['dining_table_id' => $table2->id, 'date' => Carbon::today()],
                    [
                        'client_id' => $client->id,
                        'client_name' => 'Familia Martínez',
                        'client_whatsapp' => '+584120001122',
                        'start_time' => '20:00',
                        'end_time' => '22:00',
                        'guests_count' => 4,
                        'special_requests' => 'Celebración de Aniversario. Mesa decorada.',
                        'deposit_amount_usd' => 20.00,
                        'status' => 'confirmed',
                    ]
                );
            }
        });
    }
}
