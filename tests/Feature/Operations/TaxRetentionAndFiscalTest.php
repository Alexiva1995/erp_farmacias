<?php

namespace Tests\Feature\Operations;

use App\Models\Invoice;
use App\Models\Retention;
use App\Models\Supplier;
use App\Models\User;
use App\Models\TaxUnit;
use App\Models\IslrDeclaration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxRetentionAndFiscalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Supplier $supplier;

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
            'username' => 'admin_tax',
            'email' => 'admintax@example.com',
            'password_hash' => bcrypt('password'),
            'role_id' => 1,
            'is_active' => true,
        ]);

        // 3. Crear proveedor de prueba con RIF y dirección fiscal
        $this->supplier = Supplier::create([
            'name' => 'Droguería Táchira C.A.',
            'social_reason' => 'Droguería Táchira C.A.',
            'rif' => 'J-12345678-9',
            'address' => 'Zona Industrial Paramillo, Galpón 4, San Cristóbal, Táchira',
            'is_active' => true,
            'dispatch_days' => [],
            'order_days' => [],
        ]);
    }

    /**
     * Test de ciclo de vida de Retenciones de IVA
     */
    public function test_can_manage_vat_retentions(): void
    {
        // 1. Crear factura asociada al proveedor con impuesto de IVA
        $invoice = Invoice::create([
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'FAC-0001',
            'control_number' => 'CON-0001',
            'exempt_amount' => 100.00,
            'taxable_base' => 200.00,
            'tax_amount' => 32.00, // 16% de IVA
            'total_amount' => 232.00,
            'retention_generated' => false,
            'created_invoice_date' => now(),
            'status' => 'loaded',
            'uploaded_by' => $this->admin->id,
            'registered_by' => $this->admin->id,
            'loaded_by' => $this->admin->id,
            'ordered_by' => $this->admin->id,
        ]);

        // 2. Obtener lista de facturas pendientes de retención
        $responseIndex = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/retentions?is_generated=false');

        $responseIndex->assertStatus(200)
            ->assertJsonPath('status', 'success');

        // 3. Generar la retención en lote (bulkGenerate)
        $responseGenerate = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/retentions/bulk-generate', [
                'ids' => [$invoice->id]
            ]);

        $responseGenerate->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $retentionId = $responseGenerate->json('retention_id');
        $this->assertNotNull($retentionId);

        // 4. Modificar el número del comprobante de retención
        $responseUpdate = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/retentions/{$retentionId}", [
                'number' => 'RET-2026-9999'
            ]);

        $responseUpdate->assertStatus(200)
            ->assertJsonPath('status', 'success');

        // 5. Eliminar la retención creada
        $responseDelete = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/retentions/{$retentionId}");

        $responseDelete->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Retención eliminada correctamente y facturas desvinculadas.');
    }

    /**
     * Test de límite de máximo 8 facturas por comprobante de retención
     */
    public function test_vat_retentions_chunk_at_maximum_eight_invoices_per_retention(): void
    {
        // 1. Crear 10 facturas para el mismo proveedor
        $invoiceIds = [];
        for ($i = 1; $i <= 10; $i++) {
            $inv = Invoice::create([
                'supplier_id' => $this->supplier->id,
                'invoice_number' => "FAC-000{$i}",
                'control_number' => "CON-000{$i}",
                'exempt_amount' => 10.00,
                'taxable_base' => 100.00,
                'tax_amount' => 16.00,
                'total_amount' => 126.00,
                'retention_generated' => false,
                'created_invoice_date' => now(),
                'status' => 'loaded',
                'uploaded_by' => $this->admin->id,
                'registered_by' => $this->admin->id,
                'loaded_by' => $this->admin->id,
                'ordered_by' => $this->admin->id,
            ]);
            $invoiceIds[] = $inv->id;
        }

        // 2. Generar retención con las 10 facturas seleccionadas
        $responseGenerate = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/retentions/bulk-generate', [
                'ids' => $invoiceIds
            ]);

        $responseGenerate->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $retentionIds = $responseGenerate->json('retention_ids');
        $this->assertCount(2, $retentionIds, 'Debe haber generado 2 comprobantes de retención para 10 facturas.');

        // Verificar que el primer comprobante tiene 8 facturas y el segundo 2 facturas
        $firstRetention = Retention::with('invoices')->find($retentionIds[0]);
        $secondRetention = Retention::with('invoices')->find($retentionIds[1]);

        $this->assertCount(8, $firstRetention->invoices);
        $this->assertCount(2, $secondRetention->invoices);
    }

    /**
     * Test de validación fiscal: No se genera retención a proveedores sin RIF o sin dirección fiscal
     */
    public function test_cannot_generate_retention_if_supplier_lacks_rif_or_address(): void
    {
        // Proveedor sin RIF
        $supplierNoRif = Supplier::create([
            'name' => 'Proveedor Sin RIF',
            'social_reason' => 'Proveedor Sin RIF S.A.',
            'rif' => null,
            'address' => 'Calle 1, San Cristóbal',
            'is_active' => true,
            'dispatch_days' => [],
            'order_days' => [],
        ]);

        $invoiceNoRif = Invoice::create([
            'supplier_id' => $supplierNoRif->id,
            'invoice_number' => 'FAC-NORIF-1',
            'control_number' => 'CON-NORIF-1',
            'exempt_amount' => 0,
            'taxable_base' => 100.00,
            'tax_amount' => 16.00,
            'total_amount' => 116.00,
            'retention_generated' => false,
            'created_invoice_date' => now(),
            'status' => 'loaded',
            'uploaded_by' => $this->admin->id,
            'registered_by' => $this->admin->id,
            'loaded_by' => $this->admin->id,
            'ordered_by' => $this->admin->id,
        ]);

        // Proveedor sin Dirección Fiscal
        $supplierNoAddress = Supplier::create([
            'name' => 'Proveedor Sin Dirección',
            'social_reason' => 'Proveedor Sin Dirección S.A.',
            'rif' => 'J-99999999-9',
            'address' => '',
            'is_active' => true,
            'dispatch_days' => [],
            'order_days' => [],
        ]);

        $invoiceNoAddress = Invoice::create([
            'supplier_id' => $supplierNoAddress->id,
            'invoice_number' => 'FAC-NOADDR-1',
            'control_number' => 'CON-NOADDR-1',
            'exempt_amount' => 0,
            'taxable_base' => 100.00,
            'tax_amount' => 16.00,
            'total_amount' => 116.00,
            'retention_generated' => false,
            'created_invoice_date' => now(),
            'status' => 'loaded',
            'uploaded_by' => $this->admin->id,
            'registered_by' => $this->admin->id,
            'loaded_by' => $this->admin->id,
            'ordered_by' => $this->admin->id,
        ]);

        // 1. Intento de generación manual para proveedor sin RIF (debe fallar con error 400 y mensaje explicativo)
        $responseNoRif = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/retentions/bulk-generate', [
                'ids' => [$invoiceNoRif->id]
            ]);

        $responseNoRif->assertStatus(400)
            ->assertJsonPath('status', 'error');
        $this->assertStringContainsString('RIF', $responseNoRif->json('message'));

        // 2. Intento de generación manual para proveedor sin Dirección (debe fallar con error 400)
        $responseNoAddress = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/retentions/bulk-generate', [
                'ids' => [$invoiceNoAddress->id]
            ]);

        $responseNoAddress->assertStatus(400)
            ->assertJsonPath('status', 'error');
        $this->assertStringContainsString('Dirección Fiscal', $responseNoAddress->json('message'));

        // 3. Generación en rango masivo: debe omitir ambos proveedores y retornar 0 retenciones generadas
        $responseBatch = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/retentions/batch-generate-all', [
                'start_date' => now()->subDay()->format('Y-m-d'),
                'end_date' => now()->addDay()->format('Y-m-d'),
            ]);

        $responseBatch->assertStatus(200);
        $this->assertDatabaseMissing('retentions', [
            'supplier_id' => $supplierNoRif->id,
        ]);
        $this->assertDatabaseMissing('retentions', [
            'supplier_id' => $supplierNoAddress->id,
        ]);
    }

    /**
     * Test de gestión y cálculo de ISLR
     */
    public function test_can_manage_tax_units_and_declarations(): void
    {
        // 1. Obtener la unidad tributaria activa (debe retornar 0 o notas al no haber ninguna)
        $responseTU = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/islr/tax-unit');

        $responseTU->assertStatus(200);

        // 2. Crear/Actualizar la Unidad Tributaria
        $responseUpdateTU = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/islr/tax-unit', [
                'value' => 50.00,
                'effective_date' => '2026-01-01',
                'notes' => 'Ajuste anual de unidad tributaria'
            ]);

        $this->assertTrue(in_array($responseUpdateTU->status(), [200, 201]));
        $this->assertEquals(50.00, (float) $responseUpdateTU->json('data.value'));

        // 3. Crear una nueva declaración ISLR
        $responseCreateDecl = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/islr/declarations', [
                'year' => 2026,
                'amount' => 1500.75,
                'status' => 'unpaid',
                'declaration_date' => '2026-05-29'
            ]);

        $responseCreateDecl->assertStatus(201)
            ->assertJsonPath('data.year', 2026)
            ->assertJsonPath('data.amount', '1500.75');

        $declarationId = $responseCreateDecl->json('data.id');

        // 4. Marcar declaración como pagada
        $responsePaid = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/islr/declarations/{$declarationId}/mark-paid");

        $responsePaid->assertStatus(200)
            ->assertJsonPath('data.status', 'paid');

        // 5. Eliminar la declaración ISLR
        $responseDeleteDecl = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/islr/declarations/{$declarationId}");

        $responseDeleteDecl->assertStatus(200)
            ->assertJsonPath('message', 'Declaración eliminada con éxito.');
    }
}
