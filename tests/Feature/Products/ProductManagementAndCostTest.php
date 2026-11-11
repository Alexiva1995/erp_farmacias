<?php

declare(strict_types=1);

namespace Tests\Feature\Products;

use App\Models\Category;
use App\Models\GeneralSetting;
use App\Models\Laboratory;
use App\Models\Origin;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductManagementAndCostTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Laboratory $laboratory;
    private Category $category;
    private Origin $origin;
    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['id' => 1], [
            'name' => 'Admin',
            'guard_name' => 'web',
            'menu_permissions' => ['*'],
        ]);

        $this->user = User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $this->actingAs($this->user);

        GeneralSetting::updateOrCreate(
            ['id' => 1],
            [
                'app_name' => 'Farmacia Test',
                'business_type' => 'pharmacy',
                'enable_product_types' => true,
                'default_currency' => 'USD',
            ]
        );

        $this->laboratory = Laboratory::create(['name' => 'Laboratorio Genérico']);
        $this->category = Category::create(['name' => 'Medicamentos', 'type' => 'inventory']);
        $this->origin = Origin::create(['name' => 'Nacional']);
        $this->supplier = Supplier::create([
            'name' => 'Droguería Central',
            'rif' => 'J-12345678-0',
        ]);
    }

    public function test_can_create_product_with_cost_and_purchase_units(): void
    {
        $payload = [
            'name' => 'Omeprazol 20mg Cápsulas',
            'active_ingredient' => 'Omeprazol',
            'laboratory_id' => $this->laboratory->id,
            'category_id' => $this->category->id,
            'origin_id' => $this->origin->id,
            'unit_cost' => 1.25,
            'sale_price' => 2.50,
            'purchase_unit_name' => 'Bulto',
            'pack_unit_name' => 'Caja',
            'units_per_inner_pack' => 10,
            'purchase_cost' => 12.50,
            'barcode' => '7591234567890',
            'is_active' => true,
        ];

        $response = $this->postJson('/api/products', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('products', [
            'unit_cost' => 1.25,
            'sale_price' => 2.50,
            'purchase_unit_name' => 'Bulto',
            'pack_unit_name' => 'Caja',
            'units_per_inner_pack' => 10,
            'purchase_cost' => 12.50,
            'barcode' => '7591234567890',
        ]);
    }

    public function test_can_update_product_cost_and_prices(): void
    {
        $product = Product::create([
            'name' => 'Lansoprazol 30mg',
            'laboratory_id' => $this->laboratory->id,
            'category_id' => $this->category->id,
            'origin_id' => $this->origin->id,
            'unit_cost' => 2.00,
            'sale_price' => 4.00,
            'is_active' => true,
        ]);

        $updatePayload = [
            'name' => 'Lansoprazol 30mg Forte',
            'laboratory_id' => $this->laboratory->id,
            'category_id' => $this->category->id,
            'origin_id' => $this->origin->id,
            'unit_cost' => 2.50,
            'sale_price' => 5.25,
        ];

        $response = $this->putJson("/api/products/{$product->id}", $updatePayload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'LANSOPRAZOL 30MG FORTE',
            'unit_cost' => 2.50,
            'sale_price' => 5.25,
        ]);
    }

    public function test_can_add_lots_and_query_available_stock(): void
    {
        $product = Product::create([
            'name' => 'Diclofenac 50mg',
            'laboratory_id' => $this->laboratory->id,
            'category_id' => $this->category->id,
            'origin_id' => $this->origin->id,
            'unit_cost' => 0.50,
            'sale_price' => 1.20,
            'is_active' => true,
        ]);

        // Registrar Lote 1
        $lotPayload1 = [
            'product_id' => $product->id,
            'supplier_id' => $this->supplier->id,
            'lot_number' => 'DIC-2026-A',
            'quantity' => 100,
            'unit_cost' => 0.50,
            'expiration_date' => now()->addMonths(12)->format('Y-m-d'),
        ];

        $response1 = $this->postJson('/api/product-lots', $lotPayload1);
        $response1->assertStatus(200);

        // Registrar Lote 2
        $lotPayload2 = [
            'product_id' => $product->id,
            'supplier_id' => $this->supplier->id,
            'lot_number' => 'DIC-2026-B',
            'quantity' => 50,
            'unit_cost' => 0.55,
            'expiration_date' => now()->addMonths(18)->format('Y-m-d'),
        ];

        $response2 = $this->postJson('/api/product-lots', $lotPayload2);
        $response2->assertStatus(200);

        // Consultar stock disponible por lotes
        $stockResponse = $this->getJson("/api/lots/available-stock/{$product->id}");
        $stockResponse->assertStatus(200);

        // Debe sumar 150 unidades en los lotes
        $this->assertEquals(150.0, (float) $stockResponse->json('data.lots_sum'));

        // Consultar lista detallada de lotes del producto
        $lotsResponse = $this->getJson("/api/lots/product/{$product->id}");
        $lotsResponse->assertStatus(200);
        $lots = $lotsResponse->json('data.data');
        $this->assertCount(2, $lots);
        $lotNumbers = collect($lots)->pluck('lot_number')->toArray();
        $this->assertContains('DIC-2026-A', $lotNumbers);
        $this->assertContains('DIC-2026-B', $lotNumbers);
    }

    public function test_can_toggle_active_status(): void
    {
        $product = Product::create([
            'name' => 'Complejo B Inyectable',
            'laboratory_id' => $this->laboratory->id,
            'category_id' => $this->category->id,
            'origin_id' => $this->origin->id,
            'unit_cost' => 3.00,
            'sale_price' => 6.00,
            'is_active' => true,
        ]);

        // Cambiar a inactivo
        $response = $this->postJson("/api/products/{$product->id}/toggle-active");
        $response->assertStatus(200);

        $product->refresh();
        $this->assertFalse((bool) $product->is_active);

        // Cambiar a activo nuevamente
        $response2 = $this->postJson("/api/products/{$product->id}/toggle-active");
        $response2->assertStatus(200);

        $product->refresh();
        $this->assertTrue((bool) $product->is_active);
    }

    public function test_can_soft_delete_product(): void
    {
        $product = Product::create([
            'name' => 'Producto Descontinuado',
            'laboratory_id' => $this->laboratory->id,
            'category_id' => $this->category->id,
            'origin_id' => $this->origin->id,
            'unit_cost' => 1.00,
            'sale_price' => 2.00,
            'is_active' => true,
            'is_deleted' => false,
        ]);

        $response = $this->deleteJson("/api/products/{$product->id}");
        $response->assertStatus(204);

        $product->refresh();
        $this->assertTrue((bool) $product->is_deleted);
    }
}
