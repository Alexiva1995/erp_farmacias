<?php

namespace Tests\Feature\HR;

use App\Models\Employee;
use App\Models\ExchangeRate;
use App\Models\Expense;
use App\Models\Payslip;
use App\Models\PayslipDetails;
use App\Models\Resignation;
use App\Models\Role;
use App\Models\SalaryConcept;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UsersSalaryDetails;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeePayrollAndSettlementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $employeeUser;
    private Employee $employee;
    private ExchangeRate $usdRate;
    private ExchangeRate $copRate;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Crear roles necesarios
        $role1 = new Role();
        $role1->id = 1;
        $role1->name = 'Administrador';
        $role1->save();

        $role2 = new Role();
        $role2->id = 2;
        $role2->name = 'Cajero';
        $role2->save();

        // 2. Crear usuario administrador y cajero
        $this->admin = User::create([
            'username' => 'admin_test',
            'email' => 'admin@test.com',
            'password_hash' => bcrypt('password'),
            'role_id' => 1,
            'is_active' => true,
        ]);

        $this->employeeUser = User::create([
            'username' => 'jose_cajero',
            'email' => 'jose@test.com',
            'password_hash' => bcrypt('password'),
            'role_id' => 2,
            'is_active' => true,
        ]);

        // 3. Crear el empleado asociado al usuario
        $this->employee = Employee::create([
            'name' => 'José',
            'last_name' => 'Gómez',
            'identification' => '12345678',
            'user_id' => $this->employeeUser->id,
            'total_package_usd' => 200.00,
            'is_active' => true,
        ]);

        // 4. Crear tasas de cambio requeridas
        $this->usdRate = ExchangeRate::create([
            'currency_code' => 'BS',
            'rate' => 45.00,
            'created_at' => now(),
        ]);

        $this->copRate = ExchangeRate::create([
            'currency_code' => 'COP',
            'rate' => 4000.00,
            'created_at' => now(),
        ]);

        // 5. Garantizar conceptos básicos de nómina
        $concepts = [
            'Salario Básico Mensual' => ['type' => 'salary', 'frequency' => 'fortnight'],
            'Bono de Alimentación' => ['type' => 'salary', 'frequency' => 'monthly'],
            'Asistencia Social de Salud (Art. 105 LOTTT)' => ['type' => 'salary', 'frequency' => 'monthly'],
            'Bono Extraordinario de Rendimiento' => ['type' => 'salary', 'frequency' => 'monthly'],
            'IVSS (4%)' => ['type' => 'deduction', 'frequency' => 'fortnight'],
            'RPE - Paro Forzoso (0.5%)' => ['type' => 'deduction', 'frequency' => 'fortnight'],
            'FAOV (1%)' => ['type' => 'deduction', 'frequency' => 'fortnight'],
            'Vacaciones' => ['type' => 'salary', 'frequency' => 'monthly'],
            'Bono Vacacional' => ['type' => 'salary', 'frequency' => 'monthly'],
            'Utilidades' => ['type' => 'salary', 'frequency' => 'monthly'],
        ];

        foreach ($concepts as $name => $data) {
            SalaryConcept::create([
                'name' => $name,
                'type' => $data['type'],
                'frequency' => $data['frequency'],
            ]);
        }

        // 6. Crear detalles de salario base para el usuario empleado
        $conceptBase = SalaryConcept::where('name', 'Salario Básico Mensual')->first();
        UsersSalaryDetails::create([
            'user_id' => $this->employeeUser->id,
            'salary_concept_id' => $conceptBase->id,
            'amount' => 80.00, // 80 USD mensual de base
        ]);

        // 7. Garantizar la categoría de gastos por defecto
        \App\Models\ExpenseCategory::firstOrCreate(
            ['id' => 1],
            ['name' => 'Personal/Nómina']
        );
    }

    /**
     * Test listado de recibos de nómina
     */
    public function test_can_list_payroll_payslips(): void
    {
        Payslip::create([
            'payslip_date' => now()->format('Y-m-d'),
            'name' => 'Nomina quincena (Mayo)',
            'total' => 150.00,
            'exchange_rate' => 45.00,
            'status' => 0,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/finances/payslips?itemsPerPage=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'code',
                'message',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'payslip_date',
                            'name',
                            'total',
                            'exchange_rate',
                            'status',
                        ]
                    ],
                    'total'
                ]
            ]);
    }

    /**
     * Test generación de nómina automatizada
     */
    public function test_can_generate_payroll_automatically(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/finances/payslips', [
                'date' => '2026-05-15'
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Operación exitosa'
            ]);

        $this->assertDatabaseHas('payslips', [
            'payslip_date' => '2026-05-15',
            'status' => 0,
        ]);
    }

    /**
     * Test actualización de vales de nómina variables
     */
    public function test_can_update_employee_payroll_vouchers(): void
    {
        // 1. Crear una nómina de prueba
        $payslip = Payslip::create([
            'payslip_date' => '2026-05-15',
            'name' => 'Nomina quincena (Mayo)',
            'total' => 40.00,
            'exchange_rate' => 45.00,
            'status' => 0,
        ]);

        $salaryDetail = UsersSalaryDetails::where('user_id', $this->employeeUser->id)->first();
        $payslipDetail = PayslipDetails::create([
            'payslip_id' => $payslip->id,
            'users_salary_details_id' => $salaryDetail->id,
            'amount' => 40.00,
        ]);

        // 2. Realizar petición de actualización
        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/finances/payslips/{$payslip->id}/vouchers", [
                'vouchers' => [
                    [
                        'id' => $payslipDetail->id,
                        'amount_usd' => 50.00,
                    ]
                ]
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'status' => true
                ]
            ]);

        $this->assertDatabaseHas('payslip_details', [
            'id' => $payslipDetail->id,
            'amount' => 50.00,
        ]);

        $this->assertDatabaseHas('payslips', [
            'id' => $payslip->id,
            'total' => 50.00,
        ]);
    }

    /**
     * Test cierre y finalización de nómina e impactos contables
     */
    public function test_can_finalize_payroll_and_generates_expenses_and_transactions(): void
    {
        $payslip = Payslip::create([
            'payslip_date' => '2026-05-15',
            'name' => 'Nomina quincena (Mayo)',
            'total' => 100.00, // Total 100 USD
            'exchange_rate' => 45.00,
            'status' => 0,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/finances/payslips/{$payslip->id}/finalize", [
                'currency' => 'USD',
                'count' => 'Efectivo',
                'payed' => 100.00,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'status' => true
                ]
            ]);

        // Verificar cambio de estado en la nómina
        $this->assertDatabaseHas('payslips', [
            'id' => $payslip->id,
            'status' => 1,
            'payed' => 100.00,
            'currency' => 'USD',
        ]);

        // Verificar registro automático en gastos (Expenses)
        $this->assertDatabaseHas('expenses', [
            'name' => 'Nómina',
            'amount' => 100.00,
            'total_usd' => 100.00,
            'currency' => 'USD',
            'count' => 'Efectivo',
        ]);

        // Verificar registro automático en transacciones de salida (Transactions)
        $this->assertDatabaseHas('transactions', [
            'description' => 'Pago de nómina',
            'currency' => 'USD',
            'type' => 'CASH',
            'amount' => 100.00,
            'movement_type' => 'OUT',
        ]);
    }

    /**
     * Test registro de renuncias voluntarias o despidos y estadísticas de egreso
     */
    public function test_can_register_and_get_resignation_stats(): void
    {
        $resignation = Resignation::create([
            'employee_id' => $this->employee->id,
            'employee_name' => 'José Gómez',
            'employee_identification' => '12345678',
            'employee_email' => 'jose@test.com',
            'employee_position' => 'Cajero',
            'start_date' => '2025-01-01',
            'resignation_type' => 'voluntary',
            'request_date' => '2026-05-28',
            'effective_date' => '2026-05-28',
            'employee_status' => 'Activo',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/rrhh/resignations/stats');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total' => 1,
                    'voluntary' => 1,
                    'unjustified_dismissal' => 0,
                    'active' => 1,
                ]
            ]);
    }

    /**
     * Test previsualización y cálculo correcto de liquidación/prestaciones sociales
     */
    public function test_can_calculate_employee_settlement_correctly(): void
    {
        // Forzar fecha de creación del empleado para simular antigüedad
        $this->employee->update([
            'created_at' => Carbon::parse('2025-05-28'), // Exactamente 1 año
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/rrhh/social-benefits/employees/{$this->employee->id}/settlement-data?hire_date=28/05/2025&resignation_date=28/05/2026");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'code',
                'message',
                'data' => [
                    'active_years',
                    'currency',
                    'daily_wage',
                    'integral_salary',
                    'social_benefits_days',
                    'social_benefits_amount',
                    'vacation_voucher_days',
                    'vacation_voucher_amount',
                    'vacation_bonus_voucher_days',
                    'vacation_bonus_voucher_amount',
                    'earnings_voucher_days',
                    'earnings_voucher_amount',
                    'total_settlement_amount',
                    'total_settlement_usd',
                    'final_usd',
                ]
            ]);

        $data = $response->json('data');
        // El año exacto es 1.0
        $this->assertEquals(1.0, round($data['active_years'], 1));
        // Al tener 1 año (que equivale a 12 meses, es decir >= 6 meses), le corresponden 30 días de prestaciones
        $this->assertEquals(30, $data['social_benefits_days']);
        // 15 días de vacaciones proporcionales por el primer año
        $this->assertEquals(15, $data['vacation_voucher_days']);
        // Utilidades: 30 días acumulados
        $this->assertEquals(30, $data['earnings_voucher_days']);
    }

    /**
     * Test de procesamiento de despidos, liquidación del empleado y egreso contable
     */
    public function test_can_fire_employee_successfully_and_saves_records(): void
    {
        $this->employee->update([
            'created_at' => Carbon::parse('2025-05-28'),
        ]);

        // Crear una renuncia previa
        Resignation::create([
            'employee_id' => $this->employee->id,
            'employee_name' => 'José Gómez',
            'employee_identification' => '12345678',
            'employee_email' => 'jose@test.com',
            'employee_position' => 'Cajero',
            'start_date' => '2025-05-28',
            'resignation_type' => 'unjustified_dismissal',
            'request_date' => '2026-05-28',
            'effective_date' => '2026-05-28',
            'employee_status' => 'Activo',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/rrhh/social-benefits/employees/{$this->employee->id}/fire", [
                'percentage' => 100,
                'total' => 500.00,
                'currency' => 'USD',
                'count' => 'Efectivo',
                'payed' => 500.00,
                'overrides' => [
                    'hire_date' => '28/05/2025',
                    'resignation_date' => '28/05/2026',
                    'base_salary_usd' => 150.00,
                ]
            ]);

        // El controlador genera el PDF y lo descarga (el response descarga binario PDF)
        $response->assertStatus(200);

        // Verificar que el empleado fue desactivado
        $this->assertDatabaseHas('employees', [
            'id' => $this->employee->id,
            'is_active' => false,
        ]);

        // Verificar el registro en gastos (Expenses)
        $this->assertDatabaseHas('expenses', [
            'name' => "Despido de empleado ID: {$this->employee->id}",
            'amount' => 500.00,
            'total_usd' => 500.00,
            'currency' => 'USD',
            'count' => 'Efectivo',
        ]);

        // Verificar la salida en transacciones bancarias (Transactions)
        $this->assertDatabaseHas('transactions', [
            'description' => "Despido de empleado ID: {$this->employee->id}",
            'currency' => 'USD',
            'type' => 'CASH',
            'amount' => 500.00,
            'movement_type' => 'OUT',
        ]);

        // Verificar que se guardó el histórico definitivo de liquidación
        $this->assertDatabaseHas('employee_settlements', [
            'employee_id' => $this->employee->id,
            'percentage' => 100,
            'total' => 500.00,
        ]);
    }
}
