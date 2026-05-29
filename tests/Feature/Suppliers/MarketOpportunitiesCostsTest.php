<?php

namespace Tests\Feature\Suppliers;

use App\Models\Product;
use App\Models\ProductSupplier;
use App\Models\Supplier;
use App\Models\User;
use App\Models\ProductLot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketOpportunitiesCostsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Supplier $supplier;
    private Product $product;
    private ProductSupplier $productSupplier;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear usuario para autenticación
        $this->user = User::create([
            'username' => 'testadmin',
            'email' => 'admin@test.com',
            'password_hash' => bcrypt('password'),
            'is_active' => true,
        ]);

        // Crear proveedor
        $this->supplier = Supplier::create([
            'name' => 'Proveedor de Prueba',
            'rif' => 'J-12345678-9',
            'address' => 'Dirección de prueba',
            'phone' => '1234567',
            'dispatch_days' => [],
            'order_days' => [],
        ]);

        // Crear producto con costo de inventario base
        $this->product = Product::create([
            'name' => 'Ibuprofeno 400mg',
            'unit_cost' => 5.00, // Costo actual de inventario
            'sale_price' => 8.00,
        ]);

        // Crear enlace de producto con proveedor (oferta menor al costo de inventario)
        $this->productSupplier = ProductSupplier::create([
            'product_id' => $this->product->id,
            'supplier_id' => $this->supplier->id,
            'name' => 'Ibuprofeno 400mg Genérico',
            'unit_cost_usd' => 3.00, // Costo de oferta (menor que unit_cost de inventario para que califique como oportunidad)
            'unit_cost' => 3.00,
            'connection_date' => now(),
        ]);
    }

    /**
     * Prueba que al obtener las oportunidades de mercado,
     * se retorne correctamente el costo máximo histórico (effective_max_cost)
     * calculado a partir de los lotes de los últimos 12 meses.
     */
    public function test_market_opportunities_returns_min_actual_and_max_costs(): void
    {
        // 1. Crear lotes históricos con diferentes costos
        ProductLot::create([
            'product_id' => $this->product->id,
            'supplier_id' => $this->supplier->id,
            'lot_number' => 'LOT-MIN',
            'expiration_date' => now()->addYear(),
            'quantity' => 10,
            'unit_cost' => 4.00, // Costo mínimo
            'created_at' => now(),
        ]);

        ProductLot::create([
            'product_id' => $this->product->id,
            'supplier_id' => $this->supplier->id,
            'lot_number' => 'LOT-MAX',
            'expiration_date' => now()->addYear(),
            'quantity' => 10,
            'unit_cost' => 6.00, // Costo máximo
            'created_at' => now(),
        ]);

        // 2. Consultar el endpoint de oportunidades de mercado
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/market-opportunities');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'product_id',
                        'supplier_id',
                        'unit_cost_usd',
                        'inventory_unit_cost',
                        'effective_min_cost',
                        'effective_max_cost', // Asegurar que el campo nuevo esté en la estructura
                        'saving_percentage',
                    ]
                ]
            ]);

        // 3. Validar los valores devueltos en la respuesta JSON
        $data = $response->json('data');
        $opportunity = collect($data)->firstWhere('product_id', $this->product->id);

        $this->assertNotNull($opportunity);
        $this->assertEquals(3.00, $opportunity['unit_cost_usd']); // Costo de oferta
        $this->assertEquals(5.00, $opportunity['inventory_unit_cost']); // Costo de inventario actual (en el medio)
        $this->assertEquals(4.00, $opportunity['effective_min_cost']); // Costo mínimo de los lotes
        $this->assertEquals(6.00, $opportunity['effective_max_cost']); // Costo máximo de los lotes
    }
}
