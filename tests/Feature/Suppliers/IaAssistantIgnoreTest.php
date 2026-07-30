<?php

namespace Tests\Feature\Suppliers;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IaAssistantIgnoreTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Prueba que el endpoint clear-ignored requiere autenticación.
     */
    public function test_clear_ignored_requires_authentication(): void
    {
        $response = $this->postJson('/api/suppliers-ia-order-assistant/clear-ignored');
        $response->assertStatus(401);
    }

    /**
     * Prueba que el endpoint clear-ignored restaura con éxito todos los productos ignorados.
     */
    public function test_clear_ignored_restores_products_successfully(): void
    {
        $user = User::create([
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password_hash' => bcrypt('password'),
            'is_active' => true,
        ]);

        // Crear 3 productos ignorados y 2 productos normales
        Product::create([
            'name' => 'Producto Ignorado 1',
            'unit_cost' => 10.0,
            'sale_price' => 15.0,
            'ignore_until' => now()->addDays(7),
        ]);

        Product::create([
            'name' => 'Producto Ignorado 2',
            'unit_cost' => 20.0,
            'sale_price' => 30.0,
            'ignore_until' => now()->addDays(3),
        ]);

        Product::create([
            'name' => 'Producto Normal',
            'unit_cost' => 50.0,
            'sale_price' => 75.0,
            'ignore_until' => null,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/suppliers-ia-order-assistant/clear-ignored');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Productos restaurados correctamente.',
                'restored_count' => 2,
            ]);

        // Verificar en la base de datos que todos tengan ignore_until como null
        $this->assertEquals(0, Product::whereNotNull('ignore_until')->count());
    }

    /**
     * Prueba que productos con ignore_until en el futuro no se listan a menos que show_ignored sea true.
     */
    public function test_products_with_future_ignore_until_are_not_listed_unless_show_ignored_is_true(): void
    {
        $user = User::create([
            'username' => 'testuser2',
            'email' => 'test2@example.com',
            'password_hash' => bcrypt('password'),
            'is_active' => true,
        ]);

        $ignoredProduct = Product::create([
            'name' => 'Producto Ignorado Futuro',
            'unit_cost' => 10.0,
            'sale_price' => 15.0,
            'sales_average' => 5.0,
            'stock' => 0,
            'ignore_until' => now()->addDays(7),
        ]);

        $activeProduct = Product::create([
            'name' => 'Producto Activo',
            'unit_cost' => 20.0,
            'sale_price' => 30.0,
            'sales_average' => 5.0,
            'stock' => 0,
            'ignore_until' => null,
        ]);

        // Sin show_ignored (por defecto false)
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/suppliers-ia-order-assistant/filtrar-paginate', [
                'tipo_filtracion' => 'average',
                'show_ignored' => false,
            ]);

        $response->assertStatus(200);
        $data = $response->json('data.paginate.data');
        $productIds = collect($data)->pluck('id')->toArray();

        $this->assertNotContains($ignoredProduct->id, $productIds);
        $this->assertContains($activeProduct->id, $productIds);

        // Con show_ignored = true
        $responseIgnored = $this->actingAs($user, 'sanctum')
            ->postJson('/api/suppliers-ia-order-assistant/filtrar-paginate', [
                'tipo_filtracion' => 'average',
                'show_ignored' => true,
            ]);

        $responseIgnored->assertStatus(200);
        $dataIgnored = $responseIgnored->json('data.paginate.data');
        $productIdsIgnored = collect($dataIgnored)->pluck('id')->toArray();

        $this->assertContains($ignoredProduct->id, $productIdsIgnored);
    }
}
