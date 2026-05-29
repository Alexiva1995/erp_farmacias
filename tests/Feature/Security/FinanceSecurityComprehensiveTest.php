<?php

namespace Tests\Feature\Security;

use App\Models\Payslip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceSecurityComprehensiveTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear roles necesarios
        $role1 = new \App\Models\Role();
        $role1->id = 1;
        $role1->name = 'Administrador';
        $role1->save();

        $role2 = new \App\Models\Role();
        $role2->id = 2;
        $role2->name = 'Cajero';
        $role2->save();

        // 1. Crear usuario Administrador (Rol 1)
        $this->admin = User::create([
            'username' => 'admin_security',
            'email' => 'adminsec@example.com',
            'password_hash' => bcrypt('password'),
            'role_id' => 1,
            'is_active' => true,
        ]);

        // 2. Crear usuario Cajero (Rol 2)
        $this->cashier = User::create([
            'username' => 'cashier_security',
            'email' => 'cashiersec@example.com',
            'password_hash' => bcrypt('password'),
            'role_id' => 2,
            'is_active' => true,
        ]);
    }

    /**
     * Test que valida la restricción de visibilidad de recibos de nómina (Payslips) según el Rol.
     * - Administradores (Rol 1): Pueden ver todo el historial de nóminas.
     * - Cajeros (Rol 2): Solo pueden ver los últimos 2 recibos de nómina del sistema.
     */
    public function test_payslips_visibility_is_restricted_by_role(): void
    {
        // 1. Crear 5 recibos de nómina en base de datos
        for ($i = 1; $i <= 5; $i++) {
            Payslip::create([
                'payslip_date' => "2026-05-0{$i}",
                'name' => "Nomina quincena quincena {$i}",
                'total' => 100.00 * $i,
                'exchange_rate' => 45.00,
                'status' => 1,
            ]);
        }

        // 2. Consultar como Administrador (debe ver las 5 nóminas)
        $responseAdmin = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/finances/payslips?itemsPerPage=10');

        $responseAdmin->assertStatus(200);
        $dataAdmin = $responseAdmin->json('data');
        $this->assertEquals(5, $dataAdmin['total']);
        $this->assertCount(5, $dataAdmin['data']);

        // 3. Consultar como Cajero (solo debe ver los últimos 2 recibos)
        $responseCashier = $this->actingAs($this->cashier, 'sanctum')
            ->getJson('/api/finances/payslips?itemsPerPage=10');

        $responseCashier->assertStatus(200);
        $dataCashier = $responseCashier->json('data');
        // Debe filtrar en base a la limitación de rol (máximo 2)
        $this->assertEquals(2, $dataCashier['total']);
        $this->assertCount(2, $dataCashier['data']);
    }

    /**
     * Test que valida que los cajeros o usuarios externos tengan acceso controlado o denegado
     * a las acciones críticas financieras de la empresa (Ej. Finalizar nóminas).
     */
    public function test_critical_financial_actions_require_valid_credentials(): void
    {
        // Intentar realizar una acción financiera crítica sin autenticar
        $responseUnauthenticated = $this->putJson('/api/finances/payslips/1/finalize', [
            'currency' => 'USD',
            'count' => 'Efectivo',
            'payed' => 100.00,
        ]);
        $responseUnauthenticated->assertStatus(401);
    }
}
