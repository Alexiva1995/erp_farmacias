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

        // 3. Crear proveedor de prueba
        $this->supplier = Supplier::create([
            'name' => 'Droguería Táchira C.A.',
            'social_reason' => 'Droguería Táchira C.A.',
            'rif' => 'J-12345678-9',
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
