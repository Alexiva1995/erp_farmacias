<?php

declare(strict_types=1);

namespace Tests\Feature\Invoices;

use App\Models\Invoice;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierInvoiceManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

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
            'rif' => 'J-30456789-1',
            'phone' => '02125550000',
        ]);
    }

    public function test_can_create_supplier_invoice(): void
    {
        $payload = [
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'FC-998811',
            'control_number' => '00-009988',
            'currency' => 'USD',
            'is_fiscal' => true,
            'received_date' => now()->format('Y-m-d'),
            'created_invoice_date' => now()->format('Y-m-d'),
            'exp_date' => now()->addDays(30)->format('Y-m-d'),
            'exempt_amount' => 50.00,
            'taxable_base' => 100.00,
            'tax_amount' => 16.00,
            'total_amount' => 166.00,
            'total_usd' => 166.00,
        ];

        $response = $this->postJson('/api/invoices', $payload);

        $response->assertStatus(201);
        $response->assertJson([
            'message' => 'Factura registrada con éxito.',
        ]);

        $this->assertDatabaseHas('invoices', [
            'invoice_number' => 'FC-998811',
            'supplier_id' => $this->supplier->id,
            'currency' => 'USD',
            'total_amount' => 166.00,
            'status' => 'pending',
        ]);
    }

    public function test_can_list_and_paginate_invoices(): void
    {
        Invoice::create([
            'supplier_id' => $this->supplier->id,
            'uploaded_by' => $this->user->id,
            'registered_by' => $this->user->id,
            'invoice_number' => 'FAC-001',
            'control_number' => 'CTRL-001',
            'currency' => 'USD',
            'received_date' => now()->format('Y-m-d'),
            'created_invoice_date' => now()->format('Y-m-d'),
            'exp_date' => now()->addDays(15)->format('Y-m-d'),
            'total_amount' => 120.00,
            'total_usd' => 120.00,
            'status' => 'pending',
        ]);

        $response = $this->getJson('/api/invoices');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'total',
            'total_usd_sum',
        ]);

        $this->assertGreaterThanOrEqual(1, $response->json('total'));
    }

    public function test_can_show_invoice_details(): void
    {
        $invoice = Invoice::create([
            'supplier_id' => $this->supplier->id,
            'uploaded_by' => $this->user->id,
            'registered_by' => $this->user->id,
            'invoice_number' => 'FAC-SHOW-01',
            'control_number' => 'CTRL-SHOW-01',
            'currency' => 'USD',
            'received_date' => now()->format('Y-m-d'),
            'created_invoice_date' => now()->format('Y-m-d'),
            'exp_date' => now()->addDays(20)->format('Y-m-d'),
            'total_amount' => 250.00,
            'total_usd' => 250.00,
            'status' => 'pending',
        ]);

        $response = $this->getJson("/api/invoices/{$invoice->id}");

        $response->assertStatus(200);
        $this->assertEquals('FAC-SHOW-01', $response->json('data.invoice_number'));
    }

    public function test_can_approve_invoice(): void
    {
        $invoice = Invoice::create([
            'supplier_id' => $this->supplier->id,
            'uploaded_by' => $this->user->id,
            'registered_by' => $this->user->id,
            'invoice_number' => 'FAC-APPR-01',
            'control_number' => 'CTRL-APPR-01',
            'currency' => 'USD',
            'received_date' => now()->format('Y-m-d'),
            'created_invoice_date' => now()->format('Y-m-d'),
            'exp_date' => now()->addDays(20)->format('Y-m-d'),
            'total_amount' => 300.00,
            'total_usd' => 300.00,
            'status' => 'loaded',
        ]);

        $response = $this->postJson("/api/invoices/{$invoice->id}/approve", []);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Factura aprobada con éxito.',
        ]);

        $invoice->refresh();
        $this->assertContains($invoice->status, ['to_order', 'ordered']);
    }

    public function test_can_delete_invoice(): void
    {
        $invoice = Invoice::create([
            'supplier_id' => $this->supplier->id,
            'uploaded_by' => $this->user->id,
            'registered_by' => $this->user->id,
            'invoice_number' => 'FAC-DEL-01',
            'control_number' => 'CTRL-DEL-01',
            'currency' => 'USD',
            'received_date' => now()->format('Y-m-d'),
            'created_invoice_date' => now()->format('Y-m-d'),
            'exp_date' => now()->addDays(10)->format('Y-m-d'),
            'total_amount' => 80.00,
            'total_usd' => 80.00,
            'status' => 'pending',
        ]);

        $response = $this->deleteJson("/api/invoices/{$invoice->id}");

        $response->assertStatus(204);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => 'deleted',
        ]);
    }
}
