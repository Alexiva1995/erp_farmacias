<?php

declare(strict_types=1);

namespace Tests\Feature\Wholesale;

use App\Models\Client;
use App\Models\Laboratory;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\User;
use App\Services\Wholesale\WholesaleOrderService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WholesaleOrderAndSplitTest extends TestCase
{
    use RefreshDatabase;

    protected WholesaleOrderService $orderService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesSeeder::class);
        $this->orderService = app(WholesaleOrderService::class);
    }

    public function test_drogueria_roles_are_seeded_with_default_menu_permissions(): void
    {
        $expectedRoles = [
            'Asesor Comercial',
            'Telemarketing',
            'Cobranzas y Crédito',
            'Almacén y Despacho',
        ];

        foreach ($expectedRoles as $roleName) {
            $this->assertDatabaseHas('roles', ['name' => $roleName]);
        }
    }

    public function test_wholesale_order_splits_into_suborders_by_credit_terms_option_a(): void
    {
        $seller = User::factory()->create();
        $this->actingAs($seller);

        $client = Client::create([
            'name' => 'Farmacia La Salud C.A.',
            'last_name' => 'B2B',
            'identification' => '123456789',
            'identification_type' => 'J-',
            'email' => 'compras@lasalud.com',
            'phone' => '04141234567',
            'address' => 'Av. Bolívar',
            'credit_limit_usd' => 5000.00,
            'default_credit_days' => 15,
            'sales_rep_id' => $seller->id,
        ]);

        $lab = Laboratory::create([
            'name' => 'Laboratorios Leti',
            'default_credit_days' => 15,
        ]);

        // Producto 1: Contado (0 días)
        $prodCash = Product::create([
            'name' => 'Acetaminofen 500mg',
            'sale_price' => 2.00,
            'unit_cost' => 1.20,
            'credit_days' => 0,
            'stock' => 100,
            'laboratory_id' => $lab->id,
        ]);

        ProductLot::create([
            'product_id' => $prodCash->id,
            'lot_number' => 'LOT-CASH-01',
            'quantity' => 100,
            'unit_cost' => 1.20,
            'expiration_date' => Carbon::now()->addMonths(12),
        ]);

        // Producto 2: Crédito 7 días
        $prod7 = Product::create([
            'name' => 'Amoxicilina 500mg',
            'sale_price' => 5.00,
            'unit_cost' => 3.00,
            'credit_days' => 7,
            'stock' => 50,
            'laboratory_id' => $lab->id,
        ]);

        ProductLot::create([
            'product_id' => $prod7->id,
            'lot_number' => 'LOT-7D-01',
            'quantity' => 50,
            'unit_cost' => 3.00,
            'expiration_date' => Carbon::now()->addMonths(18),
        ]);

        // Producto 3: Crédito 15 días (hereda del lab)
        $prod15 = Product::create([
            'name' => 'Loratadina 10mg',
            'sale_price' => 3.50,
            'unit_cost' => 2.10,
            'credit_days' => null, // Hereda 15 días del lab
            'stock' => 80,
            'laboratory_id' => $lab->id,
        ]);

        ProductLot::create([
            'product_id' => $prod15->id,
            'lot_number' => 'LOT-15D-01',
            'quantity' => 80,
            'unit_cost' => 2.10,
            'expiration_date' => Carbon::now()->addMonths(24),
        ]);

        $payload = [
            'client_id' => $client->id,
            'items' => [
                ['product_id' => $prodCash->id, 'quantity' => 10], // 10 * 2 = $20
                ['product_id' => $prod7->id, 'quantity' => 5],    // 5 * 5 = $25
                ['product_id' => $prod15->id, 'quantity' => 4],   // 4 * 3.5 = $14
            ],
            'notes' => 'Pedido B2B mixto para farmacia',
        ];

        $result = $this->orderService->createWholesaleOrder($payload);

        $this->assertTrue($result['success']);
        // Se deben generar 3 sub-órdenes (0 días, 7 días y 15 días)
        $this->assertEquals(3, $result['orders_count']);

        $terms = array_column($result['orders'], 'credit_term_days');
        $this->assertContains(0, $terms);
        $this->assertContains(7, $terms);
        $this->assertContains(15, $terms);
    }
}
