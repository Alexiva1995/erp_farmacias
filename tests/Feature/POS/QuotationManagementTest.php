<?php

declare(strict_types=1);

namespace Tests\Feature\POS;

use App\Models\Category;
use App\Models\Client;
use App\Models\ExchangeRate;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationProduct;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class QuotationManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Product $product;
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget('resources.all_exchange_rates');

        ExchangeRate::create(['currency_code' => 'USD', 'rate' => 1.0, 'source' => 'test']);
        ExchangeRate::create(['currency_code' => 'BS', 'rate' => 36.0, 'source' => 'test']);

        $role = Role::firstOrCreate(['id' => 1], [
            'name' => 'Admin',
            'guard_name' => 'web',
            'menu_permissions' => ['*'],
        ]);

        $this->admin = User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $category = Category::firstOrCreate(['id' => 1], ['name' => 'Farmacia', 'type' => 'inventory']);

        $this->product = Product::create([
            'name' => 'Ibuprofeno 400mg',
            'barcode' => '7591001001',
            'category_id' => $category->id,
            'product_cost' => 1.50,
            'total_cost' => 1.50,
            'price' => 3.00,
            'price_usd' => 3.00,
            'stock' => 100,
            'type' => 'product',
            'status' => 'Active',
            'is_active' => true,
            'iva' => 0,
        ]);

        $this->client = Client::create([
            'name' => 'Beatriz',
            'last_name' => 'Padrón',
            'identification' => 'V-16999888',
        ]);

        $this->actingAs($this->admin);
    }

    public function test_can_create_quotation_and_get_last_number(): void
    {
        $payload = [
            'currency' => 'USD',
            'total_amount_usd' => 15.00,
            'total_iva_usd' => 2.40,
            'grand_total_usd' => 17.40,
            'client_id' => $this->client->id,
            'products' => [
                [
                    'id' => $this->product->id,
                    'quantity' => 5,
                ]
            ],
        ];

        $response = $this->postJson('/api/tpv/quotations', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Cotización guardada exitosamente.')
            ->assertJsonPath('quotation.total', 17.4);

        $this->assertDatabaseHas('quotations', [
            'total' => 17.40,
            'client_id' => $this->client->id,
            'created_by' => $this->admin->id,
        ]);

        $this->assertDatabaseHas('quotation_products', [
            'product_id' => $this->product->id,
            'units' => 5,
        ]);

        $lastNumberResponse = $this->getJson('/api/tpv/quotations/last-number');

        $lastNumberResponse->assertStatus(200)
            ->assertJsonStructure(['quotation_id']);
    }

    public function test_can_list_quotations_and_view_associated_products(): void
    {
        $quotation = Quotation::create([
            'currency' => 'USD',
            'tax_exempt' => 0,
            'vat' => 1.60,
            'total' => 11.60,
            'client_id' => $this->client->id,
            'created_by' => $this->admin->id,
        ]);

        QuotationProduct::create([
            'quotation_id' => $quotation->id,
            'product_id' => $this->product->id,
            'units' => 3,
        ]);

        $listResponse = $this->getJson('/api/tpv/quotations/list');

        $listResponse->assertStatus(200)
            ->assertJsonStructure(['data', 'total']);

        $items = $listResponse->json('data');
        $this->assertNotEmpty($items);
        $this->assertSame($this->client->id, $items[0]['client_id']);

        $productsResponse = $this->getJson("/api/tpv/quotations/{$quotation->id}/products");

        $productsResponse->assertStatus(200)
            ->assertJsonPath('quotation_id', $quotation->id);

        $this->assertCount(1, $productsResponse->json('products'));
    }
}
