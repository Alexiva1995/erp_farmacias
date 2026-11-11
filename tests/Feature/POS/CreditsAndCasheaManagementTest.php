<?php

declare(strict_types=1);

namespace Tests\Feature\POS;

use App\Models\CashClosing;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInstallment;
use App\Models\CreditPayment;
use App\Models\ExchangeRate;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CreditsAndCasheaManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Client $client;
    private Order $order;

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

        $this->client = Client::create([
            'name' => 'Carlos',
            'last_name' => 'Gómez',
            'identification' => 'V-20111222',
            'phone' => '04141234567',
            'address' => 'Av. Principal Local 1',
        ]);

        $this->order = Order::create([
            'client_id' => $this->client->id,
            'seller_id' => $this->admin->id,
            'total_amount' => 100.00,
            'money_returns' => 0.00,
            'currency' => 'USD',
            'order_date' => now(),
            'status' => Order::COMPLETED,
        ]);

        $this->cashClosing = CashClosing::create([
            'user_id' => $this->admin->id,
            'seller_id' => $this->admin->id,
            'status' => CashClosing::OPEN,
            'closing_date' => now(),
        ]);

        $this->actingAs($this->admin);
    }

    public function test_can_list_credits_and_show_details(): void
    {
        $credit = Credit::create([
            'client_id' => $this->client->id,
            'order_id' => $this->order->id,
            'credit_amount' => 100.00,
            'pending_amount' => 100.00,
            'status' => 'Active',
            'credit_type' => 'standard',
        ]);

        $response = $this->getJson('/api/tpv/credits?itemsPerPage=10&page=1');

        $response->assertStatus(200)
            ->assertJsonPath('total', 1);

        $detailsResponse = $this->postJson('/api/tpv/credits/details', [
            'credit_ids' => [$credit->id],
        ]);

        $detailsResponse->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertCount(1, $detailsResponse->json('data'));
        $this->assertSame($this->client->id, $detailsResponse->json('data.0.client_id'));
    }

    public function test_can_get_payment_history_for_client(): void
    {
        CreditPayment::create([
            'client_id' => $this->client->id,
            'seller_id' => $this->admin->id,
            'cash_closing_id' => $this->cashClosing->id,
            'money_returns' => 0.00,
            'payment_date' => now(),
            'method_Payment' => [
                [
                    'amount' => 40.00,
                    'method' => 'Efectivo',
                    'currency' => 'USD',
                    'reference' => 'ABONO-01',
                ]
            ],
        ]);

        $response = $this->postJson('/api/tpv/credits/payments', [
            'client_id' => $this->client->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $payments = $response->json('data');
        $this->assertCount(1, $payments);
        $this->assertEquals(40.00, $payments[0]['amount']);
        $this->assertSame('ABONO-01', $payments[0]['reference']);
    }

    public function test_can_get_cashea_credits_and_kpis(): void
    {
        $casheaCredit = Credit::create([
            'client_id' => $this->client->id,
            'order_id' => $this->order->id,
            'credit_amount' => 90.00,
            'pending_amount' => 90.00,
            'status' => 'Active',
            'credit_type' => 'cashea',
            'cashea_order_reference' => 'CASHEA-REF-9988',
            'initial_payment_usd' => 30.00,
            'installments_count' => 3,
            'next_due_date' => Carbon::now()->addDays(14)->toDateString(),
        ]);

        CreditInstallment::create([
            'credit_id' => $casheaCredit->id,
            'installment_number' => 1,
            'amount_usd' => 30.00,
            'due_date' => Carbon::now()->addDays(7)->toDateString(),
            'status' => 'Pending',
        ]);

        $kpisResponse = $this->getJson('/api/tpv/credits/cashea/kpis');

        $kpisResponse->assertStatus(200);
        $this->assertEquals(90.00, $kpisResponse->json('data.total_financed_usd'));
        $this->assertEquals(90.00, $kpisResponse->json('data.total_pending_usd'));
        $this->assertEquals(30.00, $kpisResponse->json('data.due_next_14_days_usd'));

        $indexResponse = $this->getJson('/api/tpv/credits/cashea');

        $indexResponse->assertStatus(200)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.cashea_order_reference', 'CASHEA-REF-9988');
    }

    public function test_can_pay_cashea_installment(): void
    {
        $casheaCredit = Credit::create([
            'client_id' => $this->client->id,
            'order_id' => $this->order->id,
            'credit_amount' => 60.00,
            'pending_amount' => 60.00,
            'status' => 'Active',
            'credit_type' => 'cashea',
            'cashea_order_reference' => 'CASHEA-PAY-01',
            'installments_count' => 2,
            'next_due_date' => Carbon::now()->addDays(14)->toDateString(),
        ]);

        $installment1 = CreditInstallment::create([
            'credit_id' => $casheaCredit->id,
            'installment_number' => 1,
            'amount_usd' => 30.00,
            'due_date' => Carbon::now()->addDays(14)->toDateString(),
            'status' => 'Pending',
        ]);

        $installment2 = CreditInstallment::create([
            'credit_id' => $casheaCredit->id,
            'installment_number' => 2,
            'amount_usd' => 30.00,
            'due_date' => Carbon::now()->addDays(28)->toDateString(),
            'status' => 'Pending',
        ]);

        $response = $this->postJson("/api/tpv/credits/cashea/installments/{$installment1->id}/pay", [
            'amount_usd' => 30.00,
            'payment_reference' => 'CASHEA-PAGO-BATCH-1',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Cuota registrada como cobrada exitosamente.')
            ->assertJsonPath('data.status', 'Paid')
            ->assertJsonPath('data.payment_reference', 'CASHEA-PAGO-BATCH-1');

        $this->assertDatabaseHas('credit_installments', [
            'id' => $installment1->id,
            'status' => 'Paid',
            'payment_reference' => 'CASHEA-PAGO-BATCH-1',
        ]);

        $casheaCredit->refresh();
        $this->assertEquals(30.00, $casheaCredit->pending_amount);
        $this->assertSame('Active', $casheaCredit->status);
    }
}
