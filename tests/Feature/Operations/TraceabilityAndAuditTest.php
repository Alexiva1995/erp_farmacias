<?php

namespace Tests\Feature\Operations;

use App\Models\Product;
use App\Models\InventoryMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TraceabilityAndAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Product $normalProduct;
    private Product $psychotropicProduct;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Crear roles necesarios
        $role = new \App\Models\Role();
        $role->id = 1;
        $role->name = 'Administrador';
        $role->save();

        // 2. Crear administrador de prueba
        $this->admin = User::create([
            'username' => 'admin_trace',
            'email' => 'admintrace@example.com',
            'password_hash' => bcrypt('password'),
            'role_id' => 1,
            'is_active' => true,
        ]);

        // 3. Crear productos (normal y psicotrópico)
        $this->normalProduct = Product::create([
            'name' => 'Ibuprofeno 400mg',
            'barcode' => '7501234567890',
            'is_active' => true,
            'psychotropic' => false,
            'stock' => 10,
            'unit_cost' => 1.50,
            'sale_price' => 3.50,
        ]);

        $this->psychotropicProduct = Product::create([
            'name' => 'Alprazolam 2mg',
            'barcode' => '7509876543210',
            'is_active' => true,
            'psychotropic' => true,
            'stock' => 5,
            'unit_cost' => 5.00,
            'sale_price' => 12.00,
        ]);
    }

    /**
     * Test de filtro de psicotrópicos y consulta de movimientos de trazabilidad
     */
    public function test_can_filter_and_view_traceability_movements(): void
    {
        // 1. Registrar un movimiento de venta de psicotrópico
        $movement = InventoryMovement::create([
            'product_id' => $this->psychotropicProduct->id,
            'movement_type' => 'sale',
            'quantity' => 1,
            'stock_before' => 5,
            'stock_after' => 4,
            'user_id' => $this->admin->id,
            'movement_date' => now(),
        ]);

        // 2. Consultar listado general de trazabilidad
        $responseIndex = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/sales/report');

        $responseIndex->assertStatus(200)
            ->assertJsonStructure(['data', 'total']);

        // 3. Consultar listado exclusivo de Psicotrópicos (debe incluir el movimiento)
        $responsePsychotropics = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/sales/report/filterByPsychotropics');

        $responsePsychotropics->assertStatus(200)
            ->assertJsonStructure(['data', 'total']);
        
        $this->assertGreaterThanOrEqual(1, $responsePsychotropics->json('total'));

        // 4. Consultar los detalles del movimiento específico
        $responseDetails = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/sales/report/movement/{$movement->id}");

        $responseDetails->assertStatus(200)
            ->assertJsonPath('data.movement.id', $movement->id);
    }
}
