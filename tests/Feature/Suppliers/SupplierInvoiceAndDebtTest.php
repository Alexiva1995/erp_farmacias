<?php

namespace Tests\Feature\Suppliers;

use App\Models\Invoice;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierInvoiceAndDebtTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Supplier $supplier;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Crear roles necesarios
        $role = new \App\Models\Role();
        $role->id = 1;
        $role->name = 'Administrador';
        $role->save();

        // 2. Crear administrador de prueba
        $this->admin = User::create([
            'username' => 'admin_invoices',
            'email' => 'admininv@example.com',
            'password_hash' => bcrypt('password'),
            'role_id' => 1,
            'is_active' => true,
        ]);

        // 3. Crear proveedor
        $this->supplier = Supplier::create([
            'name' => 'Droguería Central',
            'rif' => 'J-123456789',
            'is_active' => true,
            'credit_days' => 15,
            'dispatch_days' => [],
            'order_days' => [],
        ]);

        // 4. Crear producto de prueba
        $this->product = Product::create([
            'name' => 'Ibuprofeno 400mg',
            'barcode' => '7501234567890',
            'is_active' => true,
            'stock' => 10,
            'unit_cost' => 5.00,
            'sale_price' => 8.00,
        ]);
    }

    /**
     * Test que valida el registro, detalle, aprobación y deudas de facturas de proveedores.
     */
    public function test_can_manage_supplier_invoices_and_calculate_debts(): void
    {
        // 1. Registrar una nueva factura de proveedor
        $responseStore = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/invoices', [
                'supplier_id' => $this->supplier->id,
                'invoice_number' => 'FAC-2026-0001',
                'control_number' => 'CTRL-0001',
                'currency' => 'USD',
                'exp_date' => '2026-06-15',
                'received_date' => '2026-05-28',
                'total_amount' => 150.00,
                'created_invoice_date' => '2026-05-28',
            ]);

        $responseStore->assertStatus(201)
            ->assertJsonPath('message', 'Factura registrada con éxito.');

        $invoiceId = $responseStore->json('invoice.id');
        $this->assertDatabaseHas('invoices', [
            'id' => $invoiceId,
            'invoice_number' => 'FAC-2026-0001',
            'status' => 'pending',
        ]);

        // 2. Obtener listado de facturas (debe estar en la lista)
        $responseIndex = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/invoices?status[]=pending');

        $responseIndex->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, $responseIndex->json('total'));

        // Finalizar la factura (pendiente -> cargada)
        $responseFinalize = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/invoices/{$invoiceId}/finalize");

        $responseFinalize->assertStatus(200);
        $this->assertDatabaseHas('invoices', [
            'id' => $invoiceId,
            'status' => 'loaded',
        ]);

        // 3. Aprobar la factura (cargada -> to_order)
        $responseApprove = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/invoices/{$invoiceId}/approve");

        $responseApprove->assertStatus(200);
        $this->assertDatabaseHas('invoices', [
            'id' => $invoiceId,
            'status' => 'to_order',
        ]);

        // 4. Calcular deudas acumuladas a proveedores (debe sumar la factura aprobada/pendiente)
        $responseDebts = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/invoices/supplier/debts');

        $responseDebts->assertStatus(200)
            ->assertJsonPath('message', 'Deudas con proveedores calculadas con éxito.');
    }

    /**
     * Test de rechazo de factura de proveedor
     */
    public function test_can_reject_supplier_invoice(): void
    {
        $invoice = Invoice::create([
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'FAC-2026-REJ',
            'control_number' => 'CTRL-REJ',
            'currency' => 'USD',
            'exp_date' => '2026-06-15',
            'received_date' => '2026-05-28',
            'total_amount' => 80.00,
            'created_invoice_date' => '2026-05-28',
            'status' => 'loaded',
            'registered_by' => $this->admin->id,
            'uploaded_by' => $this->admin->id,
        ]);

        $responseReject = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/invoices/{$invoice->id}/reject");

        $responseReject->assertStatus(200);
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => 'pending',
        ]);
    }
}
