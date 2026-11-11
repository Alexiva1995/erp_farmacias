<?php

declare(strict_types=1);

namespace Tests\Feature\Finances;

use App\Models\ExchangeRate;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PendingPaymentsManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Supplier $supplier;

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

        $this->user = User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $this->actingAs($this->user);

        $this->supplier = Supplier::create([
            'name' => 'Droguería Nena C.A.',
            'rif' => 'J-12345678-9',
            'phone' => '02125556677',
        ]);
    }

    public function test_can_get_pending_payments_grouped(): void
    {
        Invoice::create([
            'supplier_id' => $this->supplier->id,
            'uploaded_by' => $this->user->id,
            'registered_by' => $this->user->id,
            'invoice_number' => 'FAC-PAY-01',
            'control_number' => 'CTRL-01',
            'currency' => 'USD',
            'received_date' => now()->format('Y-m-d'),
            'created_invoice_date' => now()->format('Y-m-d'),
            'payment_date' => now()->format('Y-m-d'),
            'exp_date' => now()->addDays(15)->format('Y-m-d'),
            'total_amount' => 150.00,
            'total_usd' => 150.00,
            'status' => 'pending',
            'status_payment' => 0,
        ]);

        $response = $this->getJson('/api/finances/pending-payments');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
        ]);
        $this->assertGreaterThanOrEqual(1, $response->json('data.total_suppliers'));
    }

    public function test_can_get_supplier_pending_invoices(): void
    {
        Invoice::create([
            'supplier_id' => $this->supplier->id,
            'uploaded_by' => $this->user->id,
            'registered_by' => $this->user->id,
            'invoice_number' => 'FAC-PAY-SUP-01',
            'control_number' => 'CTRL-SUP-01',
            'currency' => 'USD',
            'received_date' => now()->format('Y-m-d'),
            'created_invoice_date' => now()->format('Y-m-d'),
            'payment_date' => now()->format('Y-m-d'),
            'exp_date' => now()->addDays(20)->format('Y-m-d'),
            'total_amount' => 200.00,
            'total_usd' => 200.00,
            'status' => 'loaded',
            'status_payment' => 0,
        ]);

        $response = $this->getJson("/api/finances/pending-payments/supplier/{$this->supplier->id}/invoices");

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
        ]);
        $this->assertEquals(200.00, (float) $response->json('data.total_amount'));
    }

    public function test_can_process_full_payment_for_invoice(): void
    {
        $invoice = Invoice::create([
            'supplier_id' => $this->supplier->id,
            'uploaded_by' => $this->user->id,
            'registered_by' => $this->user->id,
            'invoice_number' => 'FAC-FULL-01',
            'control_number' => 'CTRL-FULL-01',
            'currency' => 'USD',
            'received_date' => now()->format('Y-m-d'),
            'created_invoice_date' => now()->format('Y-m-d'),
            'payment_date' => now()->format('Y-m-d'),
            'exp_date' => now()->addDays(10)->format('Y-m-d'),
            'total_amount' => 100.00,
            'total_usd' => 100.00,
            'status' => 'loaded',
            'status_payment' => 0,
        ]);

        $payload = [
            'invoice_ids' => [$invoice->id],
            'payment_type' => 'full',
            'payment_currency' => 'USD',
            'payment_amount' => 100.00,
            'payment_date' => now()->format('Y-m-d'),
            'payment_method' => 'CASH',
            'reference' => 'REF-PAGO-001',
        ];

        $response = $this->postJson('/api/finances/pending-payments/process-payment', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
        ]);

        $invoice->refresh();
        $this->assertEquals(1, $invoice->status_payment);
        $this->assertDatabaseHas('invoice_payments', [
            'amount' => 100.00,
            'reference' => 'REF-PAGO-001',
        ]);
    }

    public function test_can_process_partial_payment_for_invoice(): void
    {
        $invoice = Invoice::create([
            'supplier_id' => $this->supplier->id,
            'uploaded_by' => $this->user->id,
            'registered_by' => $this->user->id,
            'invoice_number' => 'FAC-PARTIAL-01',
            'control_number' => 'CTRL-PARTIAL-01',
            'currency' => 'USD',
            'received_date' => now()->format('Y-m-d'),
            'created_invoice_date' => now()->format('Y-m-d'),
            'payment_date' => now()->format('Y-m-d'),
            'exp_date' => now()->addDays(10)->format('Y-m-d'),
            'total_amount' => 300.00,
            'total_usd' => 300.00,
            'status' => 'loaded',
            'status_payment' => 0,
        ]);

        $payload = [
            'invoice_ids' => [$invoice->id],
            'payment_type' => 'partial',
            'payment_currency' => 'USD',
            'payment_amount' => 100.00,
            'payment_date' => now()->format('Y-m-d'),
            'payment_method' => 'TRANSFER',
            'reference' => 'REF-PARTIAL-001',
        ];

        $response = $this->postJson('/api/finances/pending-payments/process-payment', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
        ]);

        $this->assertDatabaseHas('invoice_payments', [
            'amount' => 100.00,
            'reference' => 'REF-PARTIAL-001',
        ]);
    }

    public function test_can_mark_invoice_as_paid_directly(): void
    {
        $invoice = Invoice::create([
            'supplier_id' => $this->supplier->id,
            'uploaded_by' => $this->user->id,
            'registered_by' => $this->user->id,
            'invoice_number' => 'FAC-DIRECT-01',
            'control_number' => 'CTRL-DIRECT-01',
            'currency' => 'USD',
            'received_date' => now()->format('Y-m-d'),
            'created_invoice_date' => now()->format('Y-m-d'),
            'payment_date' => now()->format('Y-m-d'),
            'exp_date' => now()->addDays(5)->format('Y-m-d'),
            'total_amount' => 80.00,
            'total_usd' => 80.00,
            'status' => 'loaded',
            'status_payment' => 0,
        ]);

        $response = $this->patchJson("/api/finances/pending-payments/invoices/{$invoice->id}/mark-as-paid");

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
        ]);

        $invoice->refresh();
        $this->assertEquals(1, $invoice->status_payment);
    }
}
