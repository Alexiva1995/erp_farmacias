<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EcommerceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function can_get_public_products_and_categories()
    {
        $category = Category::factory()->create([
            'name' => 'Anillos',
            'slug' => 'anillos',
            'is_active' => true,
        ]);

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
            'stock' => 10,
            'sale_price' => 50000,
        ]);

        $response = $this->getJson('/api/public/ecommerce/categories');
        $response->assertStatus(200)
                 ->assertJsonFragment(['slug' => 'anillos']);

        $response = $this->getJson('/api/public/ecommerce/products?category=anillos');
        $response->assertStatus(200)
                 ->assertJsonFragment(['name' => $product->name]);
    }

    /** @test */
    public function can_checkout_ecommerce_order_with_variant_and_deducts_stock()
    {
        $category = Category::factory()->create(['is_active' => true]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
            'stock' => 10,
            'sale_price' => 20000
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'VAR-ANILLO-6',
            'attribute_type' => 'size',
            'attribute_value' => 'Talla 6',
            'price_modifier' => 5000,
            'stock' => 5,
        ]);

        $payload = [
            'customer_name' => 'Maria Delgado',
            'customer_email' => 'maria@example.com',
            'customer_phone' => '123456789',
            'shipping_address' => 'Av. Principal #123',
            'payment_method' => 'Simulated',
            'items' => [
                [
                    'product_id' => $product->id,
                    'variant_id' => $variant->id,
                    'quantity' => 2,
                ]
            ]
        ];

        $response = $this->postJson('/api/public/ecommerce/checkout', $payload);

        $response->assertStatus(201)
                 ->assertJsonFragment(['success' => true]);

        // Validar que se redujo el stock de la variante
        $this->assertEquals(3, $variant->fresh()->stock);

        // Validar que se registró en base de datos
        $this->assertDatabaseHas('ecommerce_orders', [
            'customer_name' => 'Maria Delgado',
            'total_amount' => 50000.00, // (20000 + 5000) * 2
        ]);
    }
}
