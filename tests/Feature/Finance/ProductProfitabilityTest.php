<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Laboratory;
use App\Models\Product;
use App\Models\ProductProfitability;
use App\Models\ProfitabilitySettings;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductProfitabilityTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Laboratory $laboratory;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['id' => 1], [
            'name' => 'Admin',
            'guard_name' => 'web',
            'menu_permissions' => ['*'],
        ]);

        $this->user = User::factory()->create(['role_id' => $role->id]);
        $this->actingAs($this->user, 'sanctum');

        $this->laboratory = Laboratory::create(['name' => 'Laboratorios Leti']);
    }

    public function test_can_get_products_for_profitability_listing(): void
    {
        $product = Product::create([
            'name' => 'Cipran 500mg x 10 Comp',
            'laboratory_id' => $this->laboratory->id,
            'unit_cost' => 5.00,
            'sale_price' => 6.50,
            'stock' => 50,
        ]);

        ProductProfitability::create([
            'product_id' => $product->id,
            'profitability_percentage' => 30.00,
            'is_locked' => 0,
        ]);

        $response = $this->getJson('/api/finances/profitability/products?itemsPerPage=10');

        $response->assertOk();
        $this->assertNotEmpty($response->json('data'));
        $this->assertEquals(1, $response->json('total'));
    }

    public function test_can_toggle_profitability_lock(): void
    {
        $product = Product::create([
            'name' => 'Teragrip Forte Sobre',
            'laboratory_id' => $this->laboratory->id,
            'unit_cost' => 1.20,
            'sale_price' => 1.80,
            'stock' => 100,
        ]);

        // 1. Toggle lock cuando no existe registro previo (debe crearlo con is_locked = 1)
        $togglePayload = [
            'product_id' => $product->id,
            'profitability_percentage' => 35.0,
        ];

        $response1 = $this->postJson('/api/finances/profitability/product/toggle-lock', $togglePayload);
        $response1->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Bloqueo de margen actualizado con éxito');

        $profitability = ProductProfitability::where('product_id', $product->id)->first();
        $this->assertNotNull($profitability);
        $this->assertEquals(1, (int) $profitability->is_locked);

        // 2. Segundo toggle lock debe desbloquear (is_locked = 0)
        $response2 = $this->postJson('/api/finances/profitability/product/toggle-lock', $togglePayload);
        $response2->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertEquals(0, (int) $profitability->fresh()->is_locked);
    }

    public function test_can_store_and_update_product_profitability(): void
    {
        $product = Product::create([
            'name' => 'Ibuprofeno 400mg',
            'laboratory_id' => $this->laboratory->id,
            'unit_cost' => 2.00,
            'sale_price' => 2.70,
            'stock' => 80,
        ]);

        // 1. Store Product Profitability
        $storePayload = [
            'product_id' => $product->id,
            'profitability_percentage' => 35.0,
            'is_locked' => 1,
            'shipping_cost' => 0.10,
            'packaging_cost' => 0.05,
            'expense_margin' => 5.0,
            'profit_margin' => 30.0,
            'tax_usa' => 0.0,
        ];

        $storeResponse = $this->postJson('/api/finances/profitability/product/store', $storePayload);
        $storeResponse->assertOk()
            ->assertJsonPath('message', 'Se ha guardado la rentabilidad del producto');

        $record = ProductProfitability::where('product_id', $product->id)->first();
        $this->assertNotNull($record);

        // 2. Update Product Profitability
        $updatePayload = [
            'id' => $record->id,
            'product_id' => $product->id,
            'profitability_percentage' => 40.0,
            'is_locked' => 1,
            'shipping_cost' => 0.15,
            'packaging_cost' => 0.05,
            'expense_margin' => 5.0,
            'profit_margin' => 35.0,
            'tax_usa' => 0.0,
        ];

        $updateResponse = $this->postJson('/api/finances/profitability/product/update', $updatePayload);
        $updateResponse->assertOk()
            ->assertJsonPath('message', 'Se ha actualizado el porcentaje');

        $this->assertEquals(40.0, (float) $record->fresh()->profitability_percentage);
    }

    public function test_can_manage_global_profitability_settings(): void
    {
        $payload = [
            'default_profitability_percentage' => 25.5,
            'shipping_cost' => 0.50,
            'packaging_cost' => 0.20,
            'expense_margin' => 8.0,
            'profit_margin' => 20.0,
            'tax_usa' => 1.5,
        ];

        $storeResponse = $this->postJson('/api/finances/profitability/store', $payload);
        $storeResponse->assertOk();

        // Consultar configuración global
        $consultResponse = $this->getJson('/api/finances/profitability');
        $consultResponse->assertOk();

        $this->assertEquals(25.5, (float) $consultResponse->json('default_profitability_percentage'));
    }
}
