<?php

declare(strict_types=1);

namespace Tests\Feature\Finances;

use App\Models\ProcessAudit;
use App\Models\ProcessFlow;
use App\Models\ProcessFlowPhase;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessAuditManagementTest extends TestCase
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

    public function test_can_create_and_list_process_flows(): void
    {
        $payload = [
            'name' => 'Flujo de Despacho Rápido',
            'description' => 'Auditoría de tiempos de preparación y entrega',
            'is_active' => true,
            'phases' => [
                [
                    'name' => 'Recepción y Facturación',
                    'description' => 'Tiempo en caja',
                    'sort_order' => 1,
                ],
                [
                    'name' => 'Picking en Almacén',
                    'description' => 'Recolección del pedido',
                    'sort_order' => 2,
                ],
                [
                    'name' => 'Entrega al Cliente',
                    'description' => 'Verificación y despacho final',
                    'sort_order' => 3,
                ],
            ],
        ];

        $response = $this->postJson('/api/finances/process-audits/flows', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Flujo de Despacho Rápido');

        $this->assertDatabaseHas('process_flows', [
            'name' => 'Flujo de Despacho Rápido',
        ]);

        $this->assertDatabaseCount('process_flow_phases', 3);

        $listResponse = $this->getJson('/api/finances/process-audits/flows');

        $listResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertCount(1, $listResponse->json('data'));
    }

    public function test_can_record_and_list_process_audits(): void
    {
        $flow = ProcessFlow::create([
            'name' => 'Flujo Mostrador',
            'is_active' => true,
        ]);

        $phase1 = ProcessFlowPhase::create([
            'flow_id' => $flow->id,
            'name' => 'Toma de Pedido',
            'sort_order' => 1,
        ]);

        $phase2 = ProcessFlowPhase::create([
            'flow_id' => $flow->id,
            'name' => 'Cobro y Empaque',
            'sort_order' => 2,
        ]);

        $auditPayload = [
            'flow_id' => $flow->id,
            'total_seconds' => 180,
            'phases' => [
                [
                    'flow_phase_id' => $phase1->id,
                    'seconds' => 60,
                ],
                [
                    'flow_phase_id' => $phase2->id,
                    'seconds' => 120,
                ],
            ],
        ];

        $storeResponse = $this->postJson('/api/finances/process-audits', $auditPayload);

        $storeResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_seconds', 180);

        $this->assertDatabaseHas('process_audits', [
            'flow_id' => $flow->id,
            'total_seconds' => 180,
        ]);

        $this->assertDatabaseCount('process_audit_phases', 2);

        $indexResponse = $this->getJson("/api/finances/process-audits?flow_id={$flow->id}");

        $indexResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('total', 1);

        $this->assertCount(1, $indexResponse->json('data'));
    }

    public function test_can_delete_process_flow(): void
    {
        $flow = ProcessFlow::create([
            'name' => 'Flujo Temporal para Borrar',
            'is_active' => true,
        ]);

        $response = $this->deleteJson("/api/finances/process-audits/flows/{$flow->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('process_flows', [
            'id' => $flow->id,
        ]);
    }
}
