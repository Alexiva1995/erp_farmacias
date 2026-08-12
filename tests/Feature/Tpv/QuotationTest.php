<?php

namespace Tests\Feature\Tpv;

use App\Models\Client;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotationTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_store_quotation()
    {
        $user = User::create([
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password_hash' => bcrypt('password'),
        ]);
        $client = Client::create(['name' => 'Juan Perez', 'identification' => 'V12345678']);
        $product = Product::factory()->create([
            'sale_price' => 10.00,
            'is_deleted' => 0,
            'is_active' => 1,
        ]);

        $response = $this->actingAs($user)->postJson('/api/tpv/quotations', [
            'total_amount_usd' => 8.00,
            'total_iva_usd' => 0,
            'grand_total_usd' => 8.00,
            'currency' => 'USD',
            'client_id' => $client->id,
            'products' => [
                [
                    'id' => $product->id,
                    'quantity' => 1,
                ]
            ]
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'quotation' => ['id', 'vat', 'total', 'client_id']
            ]);

        $this->assertDatabaseHas('quotations', [
            'total' => 8.00,
            'client_id' => $client->id,
        ]);
    }

    public function test_can_show_quotation_products()
    {
        $user = User::create([
            'name' => 'Test User 2',
            'username' => 'testuser2',
            'email' => 'test2@example.com',
            'password_hash' => bcrypt('password'),
        ]);
        $client = Client::create(['name' => 'Juan Perez', 'identification' => 'V12345678']);
        $product = Product::factory()->create();

        $storeResponse = $this->actingAs($user)->postJson('/api/tpv/quotations', [
            'total_amount_usd' => 15.00,
            'total_iva_usd' => 0,
            'grand_total_usd' => 15.00,
            'currency' => 'USD',
            'client_id' => $client->id,
            'products' => [
                [
                    'id' => $product->id,
                    'quantity' => 2,
                ]
            ]
        ]);

        $quotationId = $storeResponse->json('quotation.id');

        $response = $this->actingAs($user)->getJson("/api/tpv/quotations/{$quotationId}/products");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'quotation_id',
                'products',
                'client',
            ]);
    }
}
