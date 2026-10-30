<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\B2BOrderStatusEnum;
use App\Enums\PaymentVerificationStatusEnum;
use App\Enums\RoleEnum;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditPayment;
use App\Models\DeliveryRoute;
use App\Models\ExchangeRate;
use App\Models\GeneralSetting;
use App\Models\Laboratory;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\ProductFailure;
use App\Models\ProductLot;
use App\Models\User;
use App\Services\Import\DrogueriaMasterCatalogImportService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DrogueriaPresentationSeeder extends Seeder
{
    /**
     * Ejecuta el seeder maestro para presentaciones de la vertical Droguería B2B.
     */
    public function run(): void
    {
        $this->call(RolesSeeder::class);

        $this->seedConfiguration();
        $this->seedExchangeRates();
        $users = $this->seedDrogueriaUsers();
        $routes = $this->seedDeliveryRoutes();
        $products = $this->seedCatalogAndLots();
        $clients = $this->seedClients($users, $routes);
        $orders = $this->seedOrdersAndWmsWorkflow($clients, $users, $routes, $products);
        $this->seedCreditsAndPayments($orders, $clients, $users);
    }

    /**
     * 1. Activa la vertical de Droguería en la configuración del sistema.
     */
    protected function seedConfiguration(): void
    {
        $setting = GeneralSetting::first();
        if (!$setting) {
            $setting = new GeneralSetting();
        }

        $setting->business_type = 'drogueria';
        $setting->default_currency = 'USD';
        $setting->enable_lots = true;
        $setting->enable_expirations = true;
        $setting->enable_stock_control = true;
        $setting->tpv_mode = 'simple';
        $setting->save();
    }

    /**
     * 2. Registra tasas de cambio para simular diferencial cambiario (indexación al BCV).
     */
    protected function seedExchangeRates(): void
    {
        $now = Carbon::now();

        // Tasa histórica hace 15 días
        ExchangeRate::updateOrCreate(
            ['currency_code' => 'BS', 'created_at' => (clone $now)->subDays(15)->startOfDay()],
            ['rate' => 41.20, 'updated_at' => (clone $now)->subDays(15)]
        );

        // Tasa histórica hace 7 días
        ExchangeRate::updateOrCreate(
            ['currency_code' => 'BS', 'created_at' => (clone $now)->subDays(7)->startOfDay()],
            ['rate' => 43.10, 'updated_at' => (clone $now)->subDays(7)]
        );

        // Tasa actual de hoy
        ExchangeRate::updateOrCreate(
            ['currency_code' => 'BS', 'created_at' => (clone $now)->startOfDay()],
            ['rate' => 45.50, 'updated_at' => $now]
        );
    }

    /**
     * 3. Crea los usuarios representativos de cada rol operativo de droguería.
     *
     * @return array<string, User>
     */
    protected function seedDrogueriaUsers(): array
    {
        $password = Hash::make('12345678');

        $userProfiles = [
            'asesor_ccs' => [
                'username' => 'carlos.asesor',
                'email' => 'carlos.asesor@drogueria.com',
                'role_id' => RoleEnum::ASESOR_COMERCIAL->value,
            ],
            'asesor_central' => [
                'username' => 'maria.asesor',
                'email' => 'maria.asesor@drogueria.com',
                'role_id' => RoleEnum::ASESOR_COMERCIAL->value,
            ],
            'telemarketing' => [
                'username' => 'laura.telemarketing',
                'email' => 'laura.telemarketing@drogueria.com',
                'role_id' => RoleEnum::TELEMARKETING->value,
            ],
            'cobranzas' => [
                'username' => 'pedro.cobranzas',
                'email' => 'pedro.cobranzas@drogueria.com',
                'role_id' => RoleEnum::COBRANZAS->value,
            ],
            'almacen' => [
                'username' => 'jose.almacen',
                'email' => 'jose.almacen@drogueria.com',
                'role_id' => RoleEnum::ALMACEN_DESPACHO->value,
            ],
        ];

        $createdUsers = [];

        foreach ($userProfiles as $key => $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'username' => $data['username'],
                    'password_hash' => $password,
                    'role_id' => $data['role_id'],
                    'is_active' => true,
                ]
            );
            $createdUsers[$key] = $user;
        }

        return $createdUsers;
    }

    /**
     * 4. Registra 5 rutas logísticas estratégicas para la distribución.
     *
     * @return array<int, DeliveryRoute>
     */
    protected function seedDeliveryRoutes(): array
    {
        $routesData = [
            [
                'code' => 'RUT-CCS-01',
                'name' => 'Ruta Caracas Este (Chacao, Sucre, Baruta)',
                'zone_state' => 'Distrito Capital / Miranda',
                'driver_name' => 'Marcos Peña',
                'vehicle_plate' => 'A12BC3D (NPR 4.5 Ton)',
                'dispatch_days' => ['monday', 'wednesday', 'friday'],
                'is_active' => true,
            ],
            [
                'code' => 'RUT-CCS-02',
                'name' => 'Ruta Caracas Oeste y Altos Mirandinos',
                'zone_state' => 'Distrito Capital / Miranda',
                'driver_name' => 'Eduardo Gil',
                'vehicle_plate' => 'B45CD6E (Furgón Isuzu)',
                'dispatch_days' => ['tuesday', 'thursday', 'saturday'],
                'is_active' => true,
            ],
            [
                'code' => 'RUT-CEN-01',
                'name' => 'Ruta Centro (Valencia, Maracay, Guacara)',
                'zone_state' => 'Carabobo / Aragua',
                'driver_name' => 'Roberto Sánchez',
                'vehicle_plate' => 'C78EF9G (Camión Refrigerado)',
                'dispatch_days' => ['monday', 'thursday'],
                'is_active' => true,
            ],
            [
                'code' => 'RUT-OCC-01',
                'name' => 'Ruta Occidente (Barquisimeto, Acarigua)',
                'zone_state' => 'Lara / Portuguesa',
                'driver_name' => 'Alexander Morales',
                'vehicle_plate' => 'D01GH2J (NPR Cava Fría)',
                'dispatch_days' => ['tuesday', 'friday'],
                'is_active' => true,
            ],
            [
                'code' => 'RUT-ORI-01',
                'name' => 'Ruta Oriente (Barcelona, Lechería, Puerto La Cruz)',
                'zone_state' => 'Anzoátegui',
                'driver_name' => 'Héctor Campos',
                'vehicle_plate' => 'E34JK5L (Furgón Térmico)',
                'dispatch_days' => ['wednesday', 'saturday'],
                'is_active' => true,
            ],
        ];

        $routes = [];
        foreach ($routesData as $route) {
            $routes[] = DeliveryRoute::updateOrCreate(
                ['code' => $route['code']],
                $route
            );
        }

        return $routes;
    }

    /**
     * 5. Siembra laboratorios, catálogo farmacéutico y lotes FEFO escalonados.
     *
     * @return array<string, Product>
     */
    protected function seedCatalogAndLots(): array
    {
        $labs = [
            'Leti' => Laboratory::firstOrCreate(['name' => 'Laboratorios Leti']),
            'Calox' => Laboratory::firstOrCreate(['name' => 'Calox International']),
            'Behrens' => Laboratory::firstOrCreate(['name' => 'Laboratorios Behrens']),
            'Genven' => Laboratory::firstOrCreate(['name' => 'Genven']),
            'Farma' => Laboratory::firstOrCreate(['name' => 'Laboratorios Farma']),
        ];

        $catalog = [
            // Contado (0 días) + Cadena de frío y psicotrópicos con topes
            'insulina' => [
                'name' => 'Insulina Glargina 100 UI/ml Pluma 3ml (Cava Fría)',
                'barcode' => '7591234000011',
                'lab' => 'Behrens',
                'unit_cost' => 14.50,
                'sale_price' => 18.90,
                'credit_days' => 0,
                'is_cold_chain' => true,
                'requires_sacs' => true,
                'max_order_units' => 20,
                'lots' => [
                    ['lot' => 'LOT-INS-01', 'qty' => 80, 'months' => 6, 'loc' => 'CAVA-FRIA-01'],
                    ['lot' => 'LOT-INS-02', 'qty' => 150, 'months' => 14, 'loc' => 'CAVA-FRIA-01'],
                ],
            ],
            'clonazepam' => [
                'name' => 'Clonazepam 2mg Caja 30 Tabletas (Psicotrópico)',
                'barcode' => '7591234000028',
                'lab' => 'Leti',
                'unit_cost' => 3.20,
                'sale_price' => 4.50,
                'credit_days' => 0,
                'is_cold_chain' => false,
                'requires_sacs' => true,
                'max_order_units' => 10,
                'lots' => [
                    ['lot' => 'LOT-CLON-01', 'qty' => 60, 'months' => 8, 'loc' => 'BODEGA-SEGURIDAD'],
                    ['lot' => 'LOT-CLON-02', 'qty' => 120, 'months' => 18, 'loc' => 'BODEGA-SEGURIDAD'],
                ],
            ],
            // Crédito 7 días (Alta rotación)
            'amoxicilina' => [
                'name' => 'Amoxicilina + Ácido Clavulánico 875/125mg x 14 Tab',
                'barcode' => '7591234000035',
                'lab' => 'Calox',
                'unit_cost' => 5.20,
                'sale_price' => 7.20,
                'credit_days' => 7,
                'is_cold_chain' => false,
                'requires_sacs' => false,
                'max_order_units' => null,
                'lots' => [
                    ['lot' => 'LOT-AMOX-01', 'qty' => 200, 'months' => 5, 'loc' => 'PASILLO-B2'],
                    ['lot' => 'LOT-AMOX-02', 'qty' => 450, 'months' => 16, 'loc' => 'PASILLO-B2'],
                ],
            ],
            'azitromicina' => [
                'name' => 'Azitromicina 500mg Caja 3 Tabletas',
                'barcode' => '7591234000042',
                'lab' => 'Genven',
                'unit_cost' => 2.80,
                'sale_price' => 3.90,
                'credit_days' => 7,
                'is_cold_chain' => false,
                'requires_sacs' => false,
                'max_order_units' => null,
                'lots' => [
                    ['lot' => 'LOT-AZIT-01', 'qty' => 300, 'months' => 7, 'loc' => 'PASILLO-B3'],
                    ['lot' => 'LOT-AZIT-02', 'qty' => 600, 'months' => 20, 'loc' => 'PASILLO-B3'],
                ],
            ],
            'acetaminofen' => [
                'name' => 'Acetaminofén 650mg Caja 20 Tabletas',
                'barcode' => '7591234000059',
                'lab' => 'Leti',
                'unit_cost' => 1.20,
                'sale_price' => 1.85,
                'credit_days' => 7,
                'is_cold_chain' => false,
                'requires_sacs' => false,
                'max_order_units' => null,
                'lots' => [
                    ['lot' => 'LOT-ACET-01', 'qty' => 500, 'months' => 4, 'loc' => 'PASILLO-A1'],
                    ['lot' => 'LOT-ACET-02', 'qty' => 1200, 'months' => 18, 'loc' => 'PASILLO-A1'],
                ],
            ],
            // Crédito 15 días (Crónicos y cardiovascular)
            'losartan' => [
                'name' => 'Losartán Potásico 50mg Caja 30 Tabletas',
                'barcode' => '7591234000066',
                'lab' => 'Farma',
                'unit_cost' => 2.10,
                'sale_price' => 3.15,
                'credit_days' => 15,
                'is_cold_chain' => false,
                'requires_sacs' => false,
                'max_order_units' => null,
                'lots' => [
                    ['lot' => 'LOT-LOS-01', 'qty' => 350, 'months' => 9, 'loc' => 'PASILLO-C1'],
                    ['lot' => 'LOT-LOS-02', 'qty' => 800, 'months' => 22, 'loc' => 'PASILLO-C1'],
                ],
            ],
            'atorvastatina' => [
                'name' => 'Atorvastatina 20mg Caja 30 Tabletas',
                'barcode' => '7591234000073',
                'lab' => 'Calox',
                'unit_cost' => 3.40,
                'sale_price' => 4.95,
                'credit_days' => 15,
                'is_cold_chain' => false,
                'requires_sacs' => false,
                'max_order_units' => null,
                'lots' => [
                    ['lot' => 'LOT-ATOR-01', 'qty' => 220, 'months' => 10, 'loc' => 'PASILLO-C2'],
                    ['lot' => 'LOT-ATOR-02', 'qty' => 500, 'months' => 24, 'loc' => 'PASILLO-C2'],
                ],
            ],
            // Crédito 30 días (Metabólicos y Vitaminas)
            'metformina' => [
                'name' => 'Metformina 850mg Caja 30 Tabletas',
                'barcode' => '7591234000080',
                'lab' => 'Genven',
                'unit_cost' => 2.50,
                'sale_price' => 3.75,
                'credit_days' => 30,
                'is_cold_chain' => false,
                'requires_sacs' => false,
                'max_order_units' => null,
                'lots' => [
                    ['lot' => 'LOT-MET-01', 'qty' => 400, 'months' => 11, 'loc' => 'PASILLO-D1'],
                ],
            ],
            'vitamina_c' => [
                'name' => 'Vitamina C 500mg Masticable Frasco 100 Tab',
                'barcode' => '7591234000097',
                'lab' => 'Leti',
                'unit_cost' => 4.00,
                'sale_price' => 5.80,
                'credit_days' => 30,
                'is_cold_chain' => false,
                'requires_sacs' => false,
                'max_order_units' => null,
                'lots' => [
                    ['lot' => 'LOT-VITC-01', 'qty' => 300, 'months' => 15, 'loc' => 'PASILLO-D3'],
                ],
            ],
        ];

        $seededProducts = [];

        foreach ($catalog as $key => $item) {
            $totalStock = array_sum(array_column($item['lots'], 'qty'));

            $product = Product::updateOrCreate(
                ['barcode' => $item['barcode']],
                [
                    'name' => $item['name'],
                    'laboratory_id' => $labs[$item['lab']]->id,
                    'unit_cost' => $item['unit_cost'],
                    'sale_price' => $item['sale_price'],
                    'credit_days' => $item['credit_days'],
                    'stock' => $totalStock,
                    'iva' => true,
                    'requires_cold_chain' => $item['is_cold_chain'],
                    'max_units_per_order' => $item['max_order_units'],
                ]
            );

            foreach ($item['lots'] as $lotData) {
                ProductLot::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'lot_number' => $lotData['lot'],
                    ],
                    [
                        'expiration_date' => Carbon::now()->addMonths($lotData['months']),
                        'quantity' => $lotData['qty'],
                        'unit_cost' => $item['unit_cost'],
                        'location' => $lotData['loc'],
                    ]
                );
            }

            $seededProducts[$key] = $product;
        }

        return $seededProducts;
    }

    /**
     * 6. Registra una cartera de 12 farmacias clientes (solventes, con mora e interconectadas).
     *
     * @param array<string, User> $users
     * @param array<int, DeliveryRoute> $routes
     * @return array<int, Client>
     */
    protected function seedClients(array $users, array $routes): array
    {
        $pharmacies = [
            // Solventes - Capital
            [
                'name' => 'Farmacia San Antonio de Las Mercedes C.A.',
                'identification' => '102938475',
                'identification_type' => 'J-',
                'email' => 'compras@sanantonio.com',
                'phone' => '0212-9912345',
                'address' => 'Av. Principal de Las Mercedes, Caracas',
                'credit_limit_usd' => 15000.00,
                'default_credit_days' => 15,
                'commercial_discount_pct' => 8.0,
                'early_payment_discount_pct' => 5.0,
                'sacs_permit_number' => 'SACS-2026-FARM-0112',
                'sicm_code' => 'SICM-CCS-101',
                'sales_rep_id' => $users['asesor_ccs']->id,
                'telemarketing_user_id' => $users['telemarketing']->id,
                'collector_user_id' => $users['cobranzas']->id,
                'delivery_route_id' => $routes[0]->id,
                'linked_tenant_id' => 'farmacia_central', // Interconectada ERP
            ],
            [
                'name' => 'Botica Popular Santa Mónica C.A.',
                'identification' => '203948576',
                'identification_type' => 'J-',
                'email' => 'administracion@santamonica.com',
                'phone' => '0212-6628901',
                'address' => 'Av. Teresa de la Parra, Santa Mónica, Caracas',
                'credit_limit_usd' => 8000.00,
                'default_credit_days' => 7,
                'commercial_discount_pct' => 5.0,
                'early_payment_discount_pct' => 3.0,
                'sacs_permit_number' => 'SACS-2026-FARM-0113',
                'sicm_code' => 'SICM-CCS-102',
                'sales_rep_id' => $users['asesor_ccs']->id,
                'telemarketing_user_id' => $users['telemarketing']->id,
                'collector_user_id' => $users['cobranzas']->id,
                'delivery_route_id' => $routes[0]->id,
                'linked_tenant_id' => null,
            ],
            [
                'name' => 'Farmacia Los Altos C.A.',
                'identification' => '304958671',
                'identification_type' => 'J-',
                'email' => 'gerencia@farmacialosaltos.com',
                'phone' => '0212-3729988',
                'address' => 'C.C. La Casona, San Antonio de Los Altos',
                'credit_limit_usd' => 12000.00,
                'default_credit_days' => 15,
                'commercial_discount_pct' => 7.0,
                'early_payment_discount_pct' => 5.0,
                'sacs_permit_number' => 'SACS-2026-FARM-0114',
                'sicm_code' => 'SICM-MIR-201',
                'sales_rep_id' => $users['asesor_ccs']->id,
                'telemarketing_user_id' => $users['telemarketing']->id,
                'collector_user_id' => $users['cobranzas']->id,
                'delivery_route_id' => $routes[1]->id,
                'linked_tenant_id' => null,
            ],
            // Solventes - Centro
            [
                'name' => 'Drogas y Medicinas El Parral C.A.',
                'identification' => '405968712',
                'identification_type' => 'J-',
                'email' => 'pedidos@elparral.com',
                'phone' => '0241-8234567',
                'address' => 'Urb. El Parral, Valencia, Edo. Carabobo',
                'credit_limit_usd' => 20000.00,
                'default_credit_days' => 15,
                'commercial_discount_pct' => 10.0,
                'early_payment_discount_pct' => 5.0,
                'sacs_permit_number' => 'SACS-2026-FARM-0205',
                'sicm_code' => 'SICM-VAL-301',
                'sales_rep_id' => $users['asesor_central']->id,
                'telemarketing_user_id' => $users['telemarketing']->id,
                'collector_user_id' => $users['cobranzas']->id,
                'delivery_route_id' => $routes[2]->id,
                'linked_tenant_id' => 'farmacia_parral', // Interconectada ERP
            ],
            [
                'name' => 'Farmacia San Onofre Maracay C.A.',
                'identification' => '506978823',
                'identification_type' => 'J-',
                'email' => 'compras@sanonofre.com',
                'phone' => '0243-2467890',
                'address' => 'Av. Las Delicias, Maracay, Edo. Aragua',
                'credit_limit_usd' => 10000.00,
                'default_credit_days' => 7,
                'commercial_discount_pct' => 6.0,
                'early_payment_discount_pct' => 4.0,
                'sacs_permit_number' => 'SACS-2026-FARM-0208',
                'sicm_code' => 'SICM-MCY-305',
                'sales_rep_id' => $users['asesor_central']->id,
                'telemarketing_user_id' => $users['telemarketing']->id,
                'collector_user_id' => $users['cobranzas']->id,
                'delivery_route_id' => $routes[2]->id,
                'linked_tenant_id' => null,
            ],
            // Farmacias en Mora Activa (para alertas de crédito)
            [
                'name' => 'Farmacia La Milagrosa del Centro C.A.',
                'identification' => '607988934',
                'identification_type' => 'J-',
                'email' => 'contacto@lamilagrosa.com',
                'phone' => '0251-2521122',
                'address' => 'Carrera 19 con Calle 28, Barquisimeto',
                'credit_limit_usd' => 5000.00,
                'default_credit_days' => 7,
                'commercial_discount_pct' => 4.0,
                'early_payment_discount_pct' => 0.0,
                'sacs_permit_number' => 'SACS-2026-FARM-0310',
                'sicm_code' => 'SICM-BQTO-401',
                'sales_rep_id' => $users['asesor_central']->id,
                'telemarketing_user_id' => $users['telemarketing']->id,
                'collector_user_id' => $users['cobranzas']->id,
                'delivery_route_id' => $routes[3]->id,
                'linked_tenant_id' => null,
            ],
            [
                'name' => 'Farmacia Nueva Esparta Barcelona C.A.',
                'identification' => '708999045',
                'identification_type' => 'J-',
                'email' => 'gerencia@farmaoriente.com',
                'phone' => '0281-2864455',
                'address' => 'Av. Country Club, Barcelona, Edo. Anzoátegui',
                'credit_limit_usd' => 6000.00,
                'default_credit_days' => 7,
                'commercial_discount_pct' => 5.0,
                'early_payment_discount_pct' => 0.0,
                'sacs_permit_number' => 'SACS-2026-FARM-0420',
                'sicm_code' => 'SICM-BCN-501',
                'sales_rep_id' => $users['asesor_central']->id,
                'telemarketing_user_id' => $users['telemarketing']->id,
                'collector_user_id' => $users['cobranzas']->id,
                'delivery_route_id' => $routes[4]->id,
                'linked_tenant_id' => null,
            ],
        ];

        $clients = [];
        foreach ($pharmacies as $item) {
            $client = Client::updateOrCreate(
                ['identification' => $item['identification']],
                $item
            );
            $clients[] = $client;
        }

        return $clients;
    }

    /**
     * 7. Crea 15 pedidos B2B distribuidos por todas las etapas del WMS.
     *
     * @param array<int, Client> $clients
     * @param array<string, User> $users
     * @param array<int, DeliveryRoute> $routes
     * @param array<string, Product> $products
     * @return array<int, Order>
     */
    protected function seedOrdersAndWmsWorkflow(
        array $clients,
        array $users,
        array $routes,
        array $products
    ): array {
        $now = Carbon::now();
        $orders = [];

        // 1. Pedido Dividido por Plazo (Opción A): Farmacia San Antonio
        // Carrito original: Insulina (Contado), Amoxicilina (7 días), Losartán (15 días)
        $parentOrder = Order::create([
            'client_id' => $clients[0]->id,
            'seller_id' => $users['asesor_ccs']->id,
            'order_date' => (clone $now)->subHours(4),
            'currency' => 'USD',
            'status' => Order::PENDING,
            'b2b_workflow_status' => B2BOrderStatusEnum::APPROVED_CREDIT->value,
            'credit_term_days' => 0,
            'delivery_route_id' => $routes[0]->id,
            'packages_count' => 0,
            'cold_boxes_count' => 1,
            'payment_methods' => [],
            'taxable_base' => 173.88,
            'total_amount' => 173.88,
            'total_amount_usd' => 173.88,
            'total_cost' => 133.40,
            'money_returns' => 0,
        ]);

        OrderDetail::create([
            'order_id' => $parentOrder->id,
            'product_id' => $products['insulina']->id,
            'product_type' => 'iva',
            'quantity' => 10,
            'price' => 17.388,
            'unit_price_usd' => 17.388,
            'unit_cost' => 14.50,
            'discount_percentage' => 8.0,
            'discount_type' => 'Commercial B2B',
            'credit_days_applied' => 0,
        ]);
        $orders[] = $parentOrder;

        // Sub-orden a 7 días vinculada
        $subOrder7d = Order::create([
            'client_id' => $clients[0]->id,
            'seller_id' => $users['asesor_ccs']->id,
            'order_date' => (clone $now)->subHours(4),
            'currency' => 'USD',
            'status' => Order::PENDING,
            'b2b_workflow_status' => B2BOrderStatusEnum::PICKING->value,
            'credit_term_days' => 7,
            'parent_b2b_order_id' => $parentOrder->id,
            'delivery_route_id' => $routes[0]->id,
            'packages_count' => 1,
            'cold_boxes_count' => 0,
            'payment_methods' => [],
            'taxable_base' => 132.48,
            'total_amount' => 132.48,
            'total_amount_usd' => 132.48,
            'total_cost' => 104.00,
            'money_returns' => 0,
        ]);

        OrderDetail::create([
            'order_id' => $subOrder7d->id,
            'product_id' => $products['amoxicilina']->id,
            'product_type' => 'iva',
            'quantity' => 20,
            'picked_quantity' => 15,
            'price' => 6.624,
            'unit_price_usd' => 6.624,
            'unit_cost' => 5.20,
            'discount_percentage' => 8.0,
            'discount_type' => 'Commercial B2B',
            'credit_days_applied' => 7,
        ]);
        $orders[] = $subOrder7d;

        // Sub-orden a 15 días vinculada
        $subOrder15d = Order::create([
            'client_id' => $clients[0]->id,
            'seller_id' => $users['asesor_ccs']->id,
            'order_date' => (clone $now)->subHours(4),
            'currency' => 'USD',
            'status' => Order::PENDING,
            'b2b_workflow_status' => B2BOrderStatusEnum::CHECKING->value,
            'credit_term_days' => 15,
            'parent_b2b_order_id' => $parentOrder->id,
            'delivery_route_id' => $routes[0]->id,
            'packages_count' => 2,
            'cold_boxes_count' => 0,
            'payment_methods' => [],
            'taxable_base' => 86.94,
            'total_amount' => 86.94,
            'total_amount_usd' => 86.94,
            'total_cost' => 63.00,
            'money_returns' => 0,
        ]);

        OrderDetail::create([
            'order_id' => $subOrder15d->id,
            'product_id' => $products['losartan']->id,
            'product_type' => 'iva',
            'quantity' => 30,
            'picked_quantity' => 30,
            'checked_quantity' => 30,
            'price' => 2.898,
            'unit_price_usd' => 2.898,
            'unit_cost' => 2.10,
            'discount_percentage' => 8.0,
            'discount_type' => 'Commercial B2B',
            'credit_days_applied' => 15,
        ]);
        $orders[] = $subOrder15d;

        // 2. Pedido en PENDING_CREDIT (Para que Cobranzas apruebe sobregiro)
        $orderPendingCredit = Order::create([
            'client_id' => $clients[5]->id, // Farmacia La Milagrosa (En mora)
            'seller_id' => $users['asesor_central']->id,
            'order_date' => (clone $now)->subHours(2),
            'currency' => 'USD',
            'status' => Order::PENDING,
            'b2b_workflow_status' => B2BOrderStatusEnum::PENDING_CREDIT->value,
            'credit_term_days' => 7,
            'delivery_route_id' => $routes[3]->id,
            'packages_count' => 0,
            'cold_boxes_count' => 0,
            'payment_methods' => [],
            'taxable_base' => 1500.00,
            'total_amount' => 1500.00,
            'total_amount_usd' => 1500.00,
            'total_cost' => 1100.00,
            'money_returns' => 0,
        ]);
        $orders[] = $orderPendingCredit;

        // 3. Pedido PACKED (Listo para pulsar Despacho por Ruta)
        $orderPacked = Order::create([
            'client_id' => $clients[3]->id, // Farmacia El Parral
            'seller_id' => $users['asesor_central']->id,
            'order_date' => (clone $now)->subHours(6),
            'currency' => 'USD',
            'status' => Order::PENDING,
            'b2b_workflow_status' => B2BOrderStatusEnum::PACKED->value,
            'credit_term_days' => 15,
            'delivery_route_id' => $routes[2]->id,
            'packages_count' => 4,
            'cold_boxes_count' => 1,
            'payment_methods' => [],
            'taxable_base' => 2800.00,
            'total_amount' => 2800.00,
            'total_amount_usd' => 2800.00,
            'total_cost' => 2100.00,
            'money_returns' => 0,
        ]);
        $orders[] = $orderPacked;

        // 4. Pedido DISPATCHED (En camión hacia la farmacia)
        $orderDispatched = Order::create([
            'client_id' => $clients[1]->id, // Botica Santa Mónica
            'seller_id' => $users['asesor_ccs']->id,
            'order_date' => (clone $now)->subDay(),
            'currency' => 'USD',
            'status' => Order::PENDING,
            'b2b_workflow_status' => B2BOrderStatusEnum::DISPATCHED->value,
            'credit_term_days' => 7,
            'delivery_route_id' => $routes[0]->id,
            'packages_count' => 3,
            'cold_boxes_count' => 0,
            'payment_methods' => [],
            'taxable_base' => 1250.00,
            'total_amount' => 1250.00,
            'total_amount_usd' => 1250.00,
            'total_cost' => 920.00,
            'money_returns' => 0,
        ]);
        $orders[] = $orderDispatched;

        // Falla de stock registrada
        ProductFailure::create([
            'product_id' => $products['insulina']->id,
            'user_id' => $users['telemarketing']->id,
            'comment' => 'Demanda insatisfecha en pedido mayorista B2B (Falta de lote disponible)',
        ]);

        return $orders;
    }

    /**
     * 8. Registra Cuentas por Cobrar indexadas (Credits), abonos por conciliar y pagos aprobados.
     *
     * @param array<int, Order> $orders
     * @param array<int, Client> $clients
     * @param array<string, User> $users
     */
    protected function seedCreditsAndPayments(array $orders, array $clients, array $users): void
    {
        $now = Carbon::now();

        // Caja de cobranzas activa para recepción de abonos
        $cashClosing = \App\Models\CashClosing::create([
            'seller_id' => $users['cobranzas']->id,
            'closing_date' => $now,
            'status' => \App\Models\CashClosing::OPEN,
            'total_sales' => 0,
            'total_usd' => 0,
            'total_bs' => 0,
            'total_cop' => 0,
        ]);

        // 1. Factura Vigente (con descuento pronto pago activo)
        $creditActive = Credit::create([
            'client_id' => $clients[0]->id, // Farmacia San Antonio
            'order_id' => $orders[2]->id,
            'credit_amount' => 86.94,
            'pending_amount' => 86.94,
            'credit_date' => $now,
            'next_due_date' => (clone $now)->addDays(15)->toDateString(),
            'status' => 'Active',
            'credit_type' => 'drogueria_b2b',
            'invoice_number' => 'FAC-00089201',
            'control_number' => '00-00124451',
            'exchange_rate_issue' => 45.50,
            'is_indexed' => true,
            'early_payment_discount_pct' => 5.0,
            'early_payment_expires_at' => (clone $now)->addDays(5)->toDateString(),
            'sales_rep_id' => $users['asesor_ccs']->id,
            'collector_user_id' => $users['cobranzas']->id,
        ]);

        // 2. Factura Vencida (En Mora para reportes de Aging y Alertas)
        $creditOverdue = Credit::create([
            'client_id' => $clients[5]->id, // Farmacia La Milagrosa
            'order_id' => $orders[3]->id,
            'credit_amount' => 3200.00,
            'pending_amount' => 3200.00,
            'credit_date' => (clone $now)->subDays(25),
            'next_due_date' => (clone $now)->subDays(10)->toDateString(), // 10 días vencida
            'status' => 'Active',
            'credit_type' => 'drogueria_b2b',
            'invoice_number' => 'FAC-00088190',
            'control_number' => '00-00123399',
            'exchange_rate_issue' => 41.20,
            'is_indexed' => true,
            'early_payment_discount_pct' => 0.0,
            'sales_rep_id' => $users['asesor_central']->id,
            'collector_user_id' => $users['cobranzas']->id,
        ]);

        // 3. Factura con Abono Pendiente por Conciliar (Reportado desde Marketplace B2B)
        $creditPendingPayment = Credit::create([
            'client_id' => $clients[3]->id, // Farmacia El Parral
            'order_id' => $orders[4]->id,
            'credit_amount' => 2800.00,
            'pending_amount' => 1300.00,
            'credit_date' => (clone $now)->subDays(8),
            'next_due_date' => (clone $now)->addDays(7)->toDateString(),
            'status' => 'Active',
            'credit_type' => 'drogueria_b2b',
            'invoice_number' => 'FAC-00089100',
            'control_number' => '00-00124310',
            'exchange_rate_issue' => 43.10,
            'is_indexed' => true,
            'sales_rep_id' => $users['asesor_central']->id,
            'collector_user_id' => $users['cobranzas']->id,
        ]);

        // Abono 1: Reportado desde Marketplace B2B (Zelle / Pago Móvil) esperando validación
        CreditPayment::create([
            'client_id' => $clients[3]->id,
            'credit_id' => $creditPendingPayment->id,
            'seller_id' => $users['asesor_central']->id,
            'cash_closing_id' => $cashClosing->id,
            'money_returns' => 0,
            'status' => PaymentVerificationStatusEnum::PENDING_VERIFICATION->value,
            'reference_number' => 'REF-BANESCO-892019',
            'bank_name' => 'Banesco Banco Universal (Pago Móvil)',
            'payment_date' => (clone $now)->subHours(3),
            'exchange_rate_applied' => 45.50,
            'amount_usd_equivalent' => 1500.00,
            'source_channel' => 'marketplace_b2b',
            'withheld_iva_amount' => 180.00,
            'retention_voucher_number' => 'RET-202610-00045',
            'method_Payment' => [
                'type' => 'transfer_bs',
                'amount_bs' => 68250.00,
                'rate' => 45.50,
            ],
        ]);

        // Abono 2: Inyectado automáticamente desde el ERP de la Farmacia (erp_tenant_sync)
        CreditPayment::create([
            'client_id' => $clients[0]->id,
            'credit_id' => $creditActive->id,
            'seller_id' => $users['asesor_ccs']->id,
            'cash_closing_id' => $cashClosing->id,
            'money_returns' => 0,
            'status' => PaymentVerificationStatusEnum::PENDING_VERIFICATION->value,
            'reference_number' => 'ERP-TRANSF-MERCANTIL-7788',
            'bank_name' => 'Banco Mercantil',
            'payment_date' => (clone $now)->subMinutes(45),
            'exchange_rate_applied' => 45.50,
            'amount_usd_equivalent' => 82.59, // Con descuento pronto pago
            'source_channel' => 'erp_tenant_sync',
            'external_tenant_id' => 'farmacia_central',
            'external_invoice_payment_id' => 991,
            'method_Payment' => [
                'type' => 'usd_cash',
                'amount_usd' => 82.59,
            ],
        ]);

        // Abono 3: Ya APROBADO y Conciliado para que Asesores muestren comisiones de recaudación
        CreditPayment::create([
            'client_id' => $clients[0]->id,
            'credit_id' => $creditActive->id,
            'seller_id' => $users['asesor_ccs']->id,
            'cash_closing_id' => $cashClosing->id,
            'money_returns' => 0,
            'status' => PaymentVerificationStatusEnum::APPROVED->value,
            'reference_number' => 'REF-ZELLE-554433',
            'bank_name' => 'Zelle (Chase Bank)',
            'payment_date' => (clone $now)->subDays(3),
            'exchange_rate_applied' => 43.10,
            'amount_usd_equivalent' => 4500.00,
            'source_channel' => 'internal',
            'verified_by' => $users['cobranzas']->id,
            'verified_at' => (clone $now)->subDays(3),
            'method_Payment' => [
                'type' => 'zelle',
                'amount_usd' => 4500.00,
            ],
        ]);

        CreditPayment::create([
            'client_id' => $clients[3]->id,
            'credit_id' => $creditPendingPayment->id,
            'seller_id' => $users['asesor_central']->id,
            'cash_closing_id' => $cashClosing->id,
            'money_returns' => 0,
            'status' => PaymentVerificationStatusEnum::APPROVED->value,
            'reference_number' => 'REF-BNC-112233',
            'bank_name' => 'Banco Nacional de Crédito',
            'payment_date' => (clone $now)->subDays(2),
            'exchange_rate_applied' => 43.10,
            'amount_usd_equivalent' => 6200.00,
            'source_channel' => 'internal',
            'verified_by' => $users['cobranzas']->id,
            'verified_at' => (clone $now)->subDays(2),
            'method_Payment' => [
                'type' => 'transfer_bs',
                'amount_bs' => 267220.00,
                'rate' => 43.10,
            ],
        ]);
    }
}
