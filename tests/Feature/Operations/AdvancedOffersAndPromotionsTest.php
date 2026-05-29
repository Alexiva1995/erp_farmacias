<?php

namespace Tests\Feature\Operations;

use App\Models\Product;
use App\Models\PrescriptionOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvancedOffersAndPromotionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Product $product;

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
            'username' => 'admin_offers',
            'email' => 'adminoff@example.com',
            'password_hash' => bcrypt('password'),
            'role_id' => 1,
            'is_active' => true,
        ]);

        // 3. Crear producto de prueba
        $this->product = Product::create([
            'name' => 'Amoxicilina 500mg',
            'barcode' => '7509876543210',
            'is_active' => true,
            'stock' => 50,
            'unit_cost' => 2.00,
            'sale_price' => 5.00,
        ]);
    }

    /**
     * Test que valida el ciclo de vida de Ofertas de Recetas Médicas (Prescription Offers)
     */
    public function test_can_manage_prescription_offers(): void
    {
        // 1. Registrar una nueva oferta de receta
        $responseStore = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/tpv/promotions/prescription-offer', [
                'name' => 'Oferta Especial Pediatría',
                'discount_percentage' => 15.00,
                'start_date' => '2026-05-01',
                'end_date' => '2026-05-31',
                'is_active' => true,
            ]);

        $responseStore->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Oferta de receta creada exitosamente');

        $offerId = $responseStore->json('data.id');

        // 2. Consultar listado de ofertas de recetas
        $responseIndex = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/tpv/promotions/prescription-offer');

        $responseIndex->assertStatus(200)
            ->assertJsonPath('success', true);
        $this->assertGreaterThanOrEqual(1, $responseIndex->json('total'));

        // 3. Agregar un producto a la oferta creada mediante actualización (PUT)
        $responseAddProduct = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/tpv/promotions/prescription-offer/{$offerId}", [
                'products' => [
                    [
                        'product_id' => $this->product->id,
                        'sale_price' => 4.25,
                        'quantity' => 1,
                    ]
                ]
            ]);

        $responseAddProduct->assertStatus(200)
            ->assertJsonPath('success', true);

        // 4. Eliminar la oferta
        $responseDelete = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/tpv/promotions/prescription-offer/{$offerId}");

        $responseDelete->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Oferta de receta eliminada exitosamente');
    }
}
