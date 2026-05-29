<?php

namespace Tests\Feature\Operations;

use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeLoansAndLotteryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

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
            'username' => 'admin_loans',
            'email' => 'adminloans@example.com',
            'password_hash' => bcrypt('password'),
            'role_id' => 1,
            'is_active' => true,
        ]);
    }

    /**
     * Test de ciclo de vida de Préstamos (Loans)
     */
    public function test_can_manage_employee_loans(): void
    {
        // 1. Crear un préstamo (store)
        $responseStore = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/loans', [
                'loan_date' => '2026-05-28',
                'monthly_payment' => 100.00,
                'total_installments' => 12,
            ]);

        $responseStore->assertStatus(201)
            ->assertJsonPath('message', 'Préstamo creado con éxito.');

        $loanId = $responseStore->json('loan.id');
        $this->assertNotNull($loanId);

        // 2. Consultar listado de préstamos
        $responseIndex = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/loans');

        $responseIndex->assertStatus(200)
            ->assertJsonStructure(['data', 'total']);

        // 3. Consultar saldo general de préstamos
        $responseBalance = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/loans/balance');

        $responseBalance->assertStatus(200)
            ->assertJsonPath('message', 'Saldo de préstamos calculado con éxito.');

        // 4. Modificar el préstamo
        $responseUpdate = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/loans/{$loanId}", [
                'loan_date' => '2026-05-29',
                'monthly_payment' => 120.00,
                'total_installments' => 10,
            ]);

        $responseUpdate->assertStatus(200)
            ->assertJsonPath('message', 'Préstamo actualizado con éxito.');

        // 5. Eliminar el préstamo
        $responseDelete = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/loans/{$loanId}");

        $responseDelete->assertStatus(204);
    }

    /**
     * Test de generación de tickets o filtrado de ordenes para Sorteos (Lottery)
     */
    public function test_can_filter_orders_for_lottery(): void
    {
        // Consultar filtrado de ordenes para sorteos
        $responseFilter = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/crm/lottery/filtrar-ordenes-sin-paginar', [
                'minimo' => 20,
                'fechaDesde_filtro' => '2026-05-01',
                'fechaHasta_filtro' => '2026-05-31',
            ]);

        $responseFilter->assertStatus(200)
            ->assertJsonPath('message', 'OK');
    }
}
