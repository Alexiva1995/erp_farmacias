<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Laboratory;
use App\Models\Origin;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IncompleteProductsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        \App\Models\Role::firstOrCreate(['id' => 1], ['name' => 'Admin']);
        $this->user = User::factory()->create([
            'role_id' => 1,
            'is_active' => true,
        ]);
        Sanctum::actingAs($this->user);
    }

    public function test_can_list_incomplete_products(): void
    {
        $category = Category::factory()->create();
        $laboratory = Laboratory::create(['name' => 'Laboratorio Test']);
        $origin = Origin::create(['name' => 'Nacional']);

        // Producto completo
        Product::create([
            'name' => 'Producto Completo',
            'barcode' => '7501234567890',
            'category_id' => $category->id,
            'laboratory_id' => $laboratory->id,
            'origin_id' => $origin->id,
            'unit_cost' => 10,
            'sale_price' => 15,
            'is_active' => 1,
            'is_deleted' => 0,
        ]);

        // Producto incompleto (sin barcode ni laboratorio)
        $incompleteProduct = Product::create([
            'name' => 'Producto Incompleto 1',
            'barcode' => null,
            'category_id' => $category->id,
            'laboratory_id' => null,
            'origin_id' => null,
            'unit_cost' => 10,
            'sale_price' => 15,
            'is_active' => 1,
            'is_deleted' => 0,
        ]);

        $response = $this->getJson('/api/products/incomplete');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'barcode',
                        'laboratory_id',
                        'origin_id',
                        'stock_calculado',
                    ],
                ],
                'total',
            ]);

        $this->assertEquals(1, $response->json('total'));
        $this->assertEquals($incompleteProduct->id, $response->json('data.0.id'));
    }

    public function test_can_update_incomplete_product_fields(): void
    {
        $category = Category::factory()->create();
        $laboratory = Laboratory::create(['name' => 'Laboratorio Bayer']);
        $origin = Origin::create(['name' => 'Importado']);

        $product = Product::create([
            'name' => 'Aspirina 500mg',
            'barcode' => null,
            'category_id' => $category->id,
            'laboratory_id' => null,
            'origin_id' => null,
            'unit_cost' => 5,
            'sale_price' => 8,
            'is_active' => 1,
            'is_deleted' => 0,
        ]);

        $payload = [
            'barcode' => '7591001001001',
            'laboratory_id' => $laboratory->id,
            'origin_id' => $origin->id,
        ];

        $response = $this->patchJson("/api/products/incomplete/{$product->id}", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Producto actualizado con éxito.',
            ]);

        $product->refresh();
        $this->assertEquals('7591001001001', $product->barcode);
        $this->assertEquals($laboratory->id, $product->laboratory_id);
        $this->assertEquals($origin->id, $product->origin_id);
    }

    public function test_validates_barcode_uniqueness_on_incomplete_update(): void
    {
        $category = Category::factory()->create();

        Product::create([
            'name' => 'Producto Existente',
            'barcode' => '7599999999999',
            'category_id' => $category->id,
            'unit_cost' => 5,
            'sale_price' => 8,
            'is_active' => 1,
            'is_deleted' => 0,
        ]);

        $product = Product::create([
            'name' => 'Producto Duplicado',
            'barcode' => null,
            'category_id' => $category->id,
            'unit_cost' => 5,
            'sale_price' => 8,
            'is_active' => 1,
            'is_deleted' => 0,
        ]);

        $payload = [
            'barcode' => '7599999999999',
        ];

        $response = $this->patchJson("/api/products/incomplete/{$product->id}", $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['barcode']);
    }
}
