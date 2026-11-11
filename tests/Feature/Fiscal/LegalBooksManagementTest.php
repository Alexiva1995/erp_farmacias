<?php

declare(strict_types=1);

namespace Tests\Feature\Fiscal;

use App\Models\ExchangeRate;
use App\Models\PurchaseBook;
use App\Models\Role;
use App\Models\SalesBook;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LegalBooksManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

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
    }

    public function test_can_list_purchase_books(): void
    {
        $response = $this->getJson('/api/purchase-books');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'pagination',
        ]);
    }

    public function test_can_generate_purchase_book_period(): void
    {
        $payload = [
            'year' => 2026,
            'month' => 11,
        ];

        $response = $this->postJson('/api/purchase-books/generate-period', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Período generado exitosamente.',
        ]);

        $this->assertDatabaseHas('purchase_books', [
            'year' => 2026,
            'month' => 11,
        ]);
    }

    public function test_can_show_and_close_purchase_book(): void
    {
        $book = PurchaseBook::create([
            'year' => 2026,
            'month' => 10,
            'period_type' => 'monthly',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'status' => 'open',
            'total_exempt_amount' => 0.00,
            'total_taxable_base' => 100.00,
            'total_tax_amount' => 16.00,
            'total_purchases' => 116.00,
            'total_vat_retained' => 0.00,
        ]);

        $showResponse = $this->getJson("/api/purchase-books/{$book->id}");
        $showResponse->assertStatus(200);
        $showResponse->assertJsonPath('data.id', $book->id);

        $closeResponse = $this->postJson("/api/purchase-books/{$book->id}/close");
        $closeResponse->assertStatus(200);

        $book->refresh();
        $this->assertEquals('closed', $book->status->value);
    }

    public function test_can_list_and_generate_sales_book_period(): void
    {
        $payload = [
            'year' => 2026,
            'month' => 11,
        ];

        $generateResponse = $this->postJson('/api/sales-books/generate-period', $payload);
        $generateResponse->assertStatus(200);
        $generateResponse->assertJson([
            'message' => 'Período de ventas generado exitosamente.',
        ]);

        $listResponse = $this->getJson('/api/sales-books');
        $listResponse->assertStatus(200);
        $listResponse->assertJsonStructure([
            'data',
            'pagination',
        ]);
    }

    public function test_can_close_sales_book(): void
    {
        $book = SalesBook::create([
            'year' => 2026,
            'month' => 10,
            'period_type' => 'monthly',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'status' => 'open',
            'total_exempt_amount' => 0.00,
            'total_taxable_base' => 200.00,
            'total_tax_amount' => 32.00,
            'total_sales' => 232.00,
            'total_vat_retained' => 0.00,
        ]);

        $closeResponse = $this->postJson("/api/sales-books/{$book->id}/close");
        $closeResponse->assertStatus(200);

        $book->refresh();
        $this->assertEquals('closed', $book->status->value);
    }
}
