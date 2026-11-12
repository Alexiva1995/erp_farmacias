<?php

declare(strict_types=1);

namespace Tests\Feature\POS;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductFailureReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['id' => 1], [
            'name' => 'Admin',
            'guard_name' => 'web',
            'menu_permissions' => ['*'],
        ]);

        $this->admin = User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $category = Category::firstOrCreate(['id' => 1], ['name' => 'Antibióticos', 'type' => 'inventory']);

        $this->product = Product::create([
            'name' => 'Azitromicina 500mg',
            'barcode' => '7591005001',
            'category_id' => $category->id,
            'product_cost' => 2.50,
            'total_cost' => 2.50,
            'price' => 5.00,
            'price_usd' => 5.00,
            'stock' => 0,
            'type' => 'product',
            'status' => 'Active',
            'is_active' => true,
            'iva' => 0,
        ]);

        $this->actingAs($this->admin);
    }

    public function test_can_record_product_failure_from_pos(): void
    {
        $payload = [
            'product_id' => $this->product->id,
            'comment' => 'Cliente solicitó 3 cajas pero no hay existencia en almacén central.',
        ];

        $response = $this->postJson('/api/tpv/product-failure', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Reporte de falla guardado correctamente.')
            ->assertJsonPath('data.product_id', $this->product->id);

        $this->assertDatabaseHas('product_failures', [
            'product_id' => $this->product->id,
            'user_id' => $this->admin->id,
            'comment' => 'Cliente solicitó 3 cajas pero no hay existencia en almacén central.',
        ]);
    }
}
