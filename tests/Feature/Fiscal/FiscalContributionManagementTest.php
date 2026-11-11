<?php

declare(strict_types=1);

namespace Tests\Feature\Fiscal;

use App\Models\FiscalContribution;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FiscalContributionManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

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

    public function test_can_list_fiscal_contributions_with_kpis(): void
    {
        FiscalContribution::create([
            'period' => '05/2026',
            'tax_type' => 'IVA Retenciones',
            'document_number' => 'DOC-998877',
            'operation_date' => '2026-05-10',
            'due_date' => '2026-05-15',
            'amount' => 500.50,
            'status' => 'pending',
            'created_by' => $this->admin->id,
        ]);

        FiscalContribution::create([
            'period' => '05/2026',
            'tax_type' => 'ISLR Retenciones',
            'document_number' => 'DOC-112233',
            'operation_date' => '2026-05-10',
            'due_date' => '2026-05-12',
            'amount' => 250.00,
            'status' => 'paid',
            'payment_date' => '2026-05-11',
            'payment_reference' => 'REF-001122',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->getJson('/api/fiscal-contributions?period=05/2026');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('kpis.total_pending_count', 1)
            ->assertJsonPath('kpis.total_paid_count', 1);

        $this->assertEquals(500.5, $response->json('kpis.total_pending_amount'));
        $this->assertEquals(250.0, $response->json('kpis.total_paid_amount'));

        $this->assertCount(2, $response->json('data'));
    }

    public function test_can_create_manual_fiscal_contribution(): void
    {
        $payload = [
            'period' => '06/2026',
            'tax_type' => 'IVA General',
            'document_number' => 'SENIAT-2026-001',
            'operation_date' => '2026-06-01',
            'due_date' => '2026-06-15',
            'amount' => 1250.75,
            'status' => 'pending',
            'notes' => 'Declaración quincenal SENIAT',
        ];

        $response = $this->postJson('/api/fiscal-contributions', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.document_number', 'SENIAT-2026-001')
            ->assertJsonPath('data.amount', 1250.75);

        $this->assertDatabaseHas('fiscal_contributions', [
            'document_number' => 'SENIAT-2026-001',
            'tax_type' => 'IVA General',
            'amount' => 1250.75,
        ]);
    }

    public function test_can_parse_raw_seniat_text(): void
    {
        // Formato típico portal SENIAT: Periodo \t Impuesto \t Documento \t Fecha Operación \t Fecha Vencimiento \t Monto
        $rawSeniat = "Periodo\tImpuesto\tDocumento\tFecha de Operación\tFecha de Vencimiento\tMonto\n" .
                     "05/2026\tIVA Retenciones\t00009988\t10/05/2026\t15/05/2026\t11.639,05\n" .
                     "05/2026\tISLR\t00009989\t11/05/2026\t18/05/2026\t3.450,50";

        $response = $this->postJson('/api/fiscal-contributions/parse-seniat', [
            'raw_text' => $rawSeniat,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('count', 2);

        $data = $response->json('data');
        $this->assertSame('05/2026', $data[0]['period']);
        $this->assertSame('00009988', $data[0]['document_number']);
        $this->assertSame('2026-05-10', $data[0]['operation_date']);
        $this->assertSame(11639.05, $data[0]['amount']);
    }

    public function test_can_batch_import_fiscal_contributions(): void
    {
        $items = [
            [
                'period' => '07/2026',
                'tax_type' => 'IVA Declaración',
                'document_number' => 'BATCH-001',
                'operation_date' => '2026-07-01',
                'due_date' => '2026-07-15',
                'amount' => 450.00,
                'status' => 'pending',
            ],
            [
                'period' => '07/2026',
                'tax_type' => 'ISLR Anticipo',
                'document_number' => 'BATCH-002',
                'operation_date' => '2026-07-01',
                'due_date' => '2026-07-15',
                'amount' => 300.00,
                'status' => 'pending',
            ]
        ];

        $response = $this->postJson('/api/fiscal-contributions/batch-import', [
            'items' => $items,
            'source' => 'smart_paste',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('result.total', 2)
            ->assertJsonPath('result.inserted', 2);

        $this->assertDatabaseHas('fiscal_contributions', [
            'document_number' => 'BATCH-001',
            'amount' => 450.00,
        ]);

        $this->assertDatabaseHas('fiscal_contributions', [
            'document_number' => 'BATCH-002',
            'amount' => 300.00,
        ]);
    }

    public function test_can_toggle_payment_status_for_contribution(): void
    {
        $contribution = FiscalContribution::create([
            'period' => '08/2026',
            'tax_type' => 'Retención IVA 75%',
            'document_number' => 'TOGGLE-DOC-1',
            'operation_date' => '2026-08-01',
            'due_date' => '2026-08-10',
            'amount' => 880.00,
            'status' => 'pending',
            'created_by' => $this->admin->id,
        ]);

        // 1. Marcar como pagado
        $responsePaid = $this->patchJson("/api/fiscal-contributions/{$contribution->id}/toggle-payment", [
            'status' => 'paid',
            'payment_date' => '2026-08-05',
            'payment_reference' => 'PAGO-BANCO-7788',
        ]);

        $responsePaid->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.payment_reference', 'PAGO-BANCO-7788');

        $this->assertDatabaseHas('fiscal_contributions', [
            'id' => $contribution->id,
            'status' => 'paid',
            'payment_reference' => 'PAGO-BANCO-7788',
        ]);

        // 2. Devolver a pendiente
        $responsePending = $this->patchJson("/api/fiscal-contributions/{$contribution->id}/toggle-payment", [
            'status' => 'pending',
        ]);

        $responsePending->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('fiscal_contributions', [
            'id' => $contribution->id,
            'status' => 'pending',
        ]);
    }
}
