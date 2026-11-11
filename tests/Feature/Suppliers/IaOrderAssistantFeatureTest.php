<?php

declare(strict_types=1);

namespace Tests\Feature\Suppliers;

use App\Models\ExchangeRate;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\ProductSupplier;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class IaOrderAssistantFeatureTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Supplier $supplier;
    private Product $product;
    private ProductSupplier $productSupplier;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget('resources.all_exchange_rates');

        ExchangeRate::create(['currency_code' => 'USD', 'rate' => 1.0, 'source' => 'test']);
        ExchangeRate::create(['currency_code' => 'BS', 'rate' => 36.0, 'source' => 'test']);
        ExchangeRate::create(['currency_code' => 'COP', 'rate' => 4000.0, 'source' => 'test']);

        $role = Role::firstOrCreate(['id' => 1], [
            'name' => 'Admin',
            'guard_name' => 'web',
            'menu_permissions' => ['*'],
        ]);

        $this->admin = User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin);

        $this->supplier = Supplier::create([
            'name' => 'Droguería Nena C.A.',
            'rif' => 'J-12345678-0',
            'phone' => '02125550000',
        ]);

        $this->product = Product::create([
            'name' => 'Loratadina 10mg',
            'unit_cost' => 1.50,
            'sale_price' => 3.00,
            'iva' => 0,
        ]);

        ProductLot::create([
            'product_id' => $this->product->id,
            'supplier_id' => $this->supplier->id,
            'lot_number' => 'LOT-IA-01',
            'expiration_date' => now()->addYear()->format('Y-m-d'),
            'quantity' => 10,
            'unit_cost' => 1.50,
        ]);

        $this->productSupplier = ProductSupplier::create([
            'product_id' => $this->product->id,
            'supplier_id' => $this->supplier->id,
            'unit_cost' => 1.50,
            'unit_cost_usd' => 1.50,
            'connection_date' => now(),
        ]);
    }

    public function test_can_filter_ia_order_assistant_report(): void
    {
        $payload = [
            'page' => 1,
            'itemsPerPage' => 10,
            'tipo_filtracion' => 'average',
            'stock' => 'all',
        ];

        $response = $this->postJson('/api/suppliers-ia-order-assistant/filtrar-paginate', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
        ]);
        $this->assertNotNull($response->json('data.paginate'));
    }

    public function test_can_get_ia_order_assistant_stats(): void
    {
        $payload = [
            'tipo_filtracion' => 'average',
            'stock' => 'all',
        ];

        $response = $this->postJson('/api/suppliers-ia-order-assistant/stats', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
        ]);
        $this->assertArrayHasKey('necesitan', $response->json('data'));
        $this->assertArrayHasKey('exceso', $response->json('data'));
        $this->assertArrayHasKey('ok', $response->json('data'));
    }

    public function test_can_add_product_to_auto_order(): void
    {
        $payload = [
            'product_id' => $this->product->id,
            'supplier_id' => $this->supplier->id,
            'product_supplier_id' => $this->productSupplier->id,
            'quantity' => 20,
            'unit_cost' => 1.50,
        ];

        $response = $this->postJson('/api/suppliers-ia-order-assistant/add-to-order', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Producto añadido a la orden correctamente.',
        ]);

        $this->assertDatabaseHas('auto_orders', [
            'supplier_id' => $this->supplier->id,
        ]);
    }

    public function test_can_ignore_and_clear_ignored_product(): void
    {
        $ignoreResponse = $this->postJson("/api/suppliers-ia-order-assistant/products/{$this->product->id}/ignore");
        $ignoreResponse->assertStatus(200);

        $clearResponse = $this->postJson('/api/suppliers-ia-order-assistant/clear-ignored');
        $clearResponse->assertStatus(200);
    }
}
