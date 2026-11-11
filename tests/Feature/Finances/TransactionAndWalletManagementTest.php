<?php

declare(strict_types=1);

namespace Tests\Feature\Finances;

use App\Models\ExchangeRate;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TransactionAndWalletManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private \App\Models\ExpenseCategory $category;

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

        \App\Models\Category::firstOrCreate(['id' => 1], ['name' => 'General Cat', 'type' => 'inventory']);
        \App\Models\ExpenseCategory::firstOrCreate(['id' => 1], ['name' => 'General Exp']);

        $this->actingAs($this->admin);
    }

    public function test_can_list_transactions(): void
    {
        Transaction::create([
            'user_id' => $this->admin->id,
            'category_id' => 1,
            'description' => 'Venta en efectivo USD',
            'currency' => 'USD',
            'type' => 'CASH',
            'amount' => 50.00,
            'movement_type' => 'IN',
            'transaction_date' => now()->toDateString(),
            'exchange_rate' => 1.0000,
        ]);

        $response = $this->getJson('/api/finances/transactions');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
        ]);
        $this->assertGreaterThanOrEqual(1, $response->json('data.total'));
    }

    public function test_can_get_wallets_summary(): void
    {
        Transaction::create([
            'user_id' => $this->admin->id,
            'category_id' => 1,
            'description' => 'Ingreso Caja COP',
            'currency' => 'COP',
            'type' => 'CASH',
            'amount' => 100000.00,
            'movement_type' => 'IN',
            'transaction_date' => now()->toDateString(),
            'exchange_rate' => 1.0000,
        ]);

        $response = $this->getJson('/api/finances/transactions/wallets');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
        ]);
        $this->assertNotNull($response->json('data'));
    }

    public function test_can_transfer_between_wallets(): void
    {
        Transaction::create([
            'user_id' => $this->admin->id,
            'category_id' => 1,
            'description' => 'Fondo inicial USD',
            'currency' => 'USD',
            'type' => 'CASH',
            'amount' => 200.00,
            'movement_type' => 'IN',
            'transaction_date' => now()->toDateString(),
            'exchange_rate' => 1.0000,
        ]);

        $payload = [
            'source_currency' => 'USD',
            'source_type' => 'CASH',
            'source_amount' => 50.00,
            'destination_currency' => 'USD',
            'destination_type' => 'TRANSFER',
            'destination_amount' => 50.00,
            'exchange_rate' => 1.0000,
            'notes' => 'Transferencia a cuenta bancaria',
        ];

        $response = $this->postJson('/api/finances/transactions/transfer', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'message' => 'Transferencia entre cajas realizada correctamente',
        ]);

        $this->assertDatabaseHas('transactions', [
            'currency' => 'USD',
            'type' => 'TRANSFER',
            'amount' => 50.00,
            'movement_type' => 'IN',
        ]);
    }

    public function test_can_adjust_balance_as_admin(): void
    {
        Transaction::create([
            'user_id' => $this->admin->id,
            'description' => 'Saldo previo',
            'currency' => 'USD',
            'type' => 'CASH',
            'amount' => 80.00,
            'movement_type' => 'IN',
            'transaction_date' => now()->toDateString(),
            'exchange_rate' => 1.0000,
        ]);

        $payload = [
            'currency' => 'USD',
            'type' => 'CASH',
            'new_balance' => 100.00,
        ];

        $response = $this->postJson('/api/finances/transactions/adjustment', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'message' => 'Saldo ajustado correctamente',
        ]);
    }
}
