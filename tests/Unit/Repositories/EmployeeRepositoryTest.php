<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\Client;
use App\Models\Employee;
use App\Models\ExchangeRate;
use App\Models\Order;
use App\Models\Payslip;
use App\Models\PayslipDetails;
use App\Models\Role;
use App\Models\SalaryConcept;
use App\Models\User;
use App\Models\UsersSalaryDetails;
use App\Repositories\EmployeeRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class EmployeeRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private EmployeeRepository $repository;
    private Role $adminRole;
    private Role $sellerRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new EmployeeRepository();

        $this->adminRole = Role::firstOrCreate(
            ['id' => 1],
            ['name' => 'Admin', 'guard_name' => 'web', 'menu_permissions' => ['*']]
        );
        $this->sellerRole = Role::firstOrCreate(
            ['id' => 2],
            ['name' => 'Vendedor', 'guard_name' => 'web', 'menu_permissions' => ['pos']]
        );

        ExchangeRate::create(['currency_code' => 'BS', 'rate' => 45.0, 'source' => 'test']);
        ExchangeRate::create(['currency_code' => 'COP', 'rate' => 4200.0, 'source' => 'test']);
        ExchangeRate::create(['currency_code' => 'COPS', 'rate' => 4200.0, 'source' => 'test']);
    }

    public function test_stores_lists_profiles_updates_fires_resets_2fa_and_deletes_employee(): void
    {
        $created = $this->repository->store([
            'name' => 'Carlos',
            'last_name' => 'Mendoza',
            'identification' => '18111222',
            'email' => 'carlos@test.com',
            'password' => 'secret123',
            'role' => $this->sellerRole->id,
            'total_package_usd' => 240.00,
        ]);

        $this->assertTrue($created);

        $employee = Employee::where('identification', '18111222')->firstOrFail();
        $this->assertSame('Carlos', $employee->name);
        $this->assertTrue((bool) $employee->is_active);
        $this->assertDatabaseHas('salary_concepts', ['name' => 'Bono de Alimentación']);

        // Listado activo y filtro por búsqueda
        $activeList = $this->repository->list([
            'search' => 'Carlos',
            'active' => 'true',
            'perPage' => 10,
        ]);
        $this->assertSame(1, $activeList->total());

        $inactiveEmpty = $this->repository->list([
            'active' => 'false',
            'perPage' => 10,
        ]);
        $this->assertSame(0, $inactiveEmpty->total());

        // Perfil de empleado
        $profile = $this->repository->profile($employee);
        $this->assertSame($employee->id, $profile->id);

        // Actualización de empleado y usuario asociado
        $updated = $this->repository->update($employee, [
            'name' => 'Carlos Alberto',
            'last_name' => 'Mendoza Ruiz',
            'identification' => '18111222',
            'email' => 'carlos.nuevo@test.com',
            'password' => 'newpass456',
            'role' => $this->adminRole->id,
            'total_package_usd' => 300.00,
        ]);
        $this->assertTrue($updated);
        $this->assertSame('Carlos Alberto', $employee->fresh()->name);
        $this->assertSame($this->adminRole->id, $employee->user->fresh()->role_id);

        // Reinicio de 2FA
        $this->assertTrue($this->repository->reset2FA($employee));

        // Desincorporación (fire)
        $this->assertTrue($this->repository->fire($employee));
        $this->assertFalse((bool) $employee->fresh()->is_active);

        $inactiveList = $this->repository->list(['active' => 'false', 'perPage' => 10]);
        $this->assertSame(1, $inactiveList->total());

        // Desactivación vía deleteEmployee (desactiva el registro sin borrar la fila)
        $employee->update(['is_active' => true]);
        $this->assertTrue($this->repository->deleteEmployee($employee));
        $this->assertFalse((bool) $employee->fresh()->is_active);
    }

    public function test_manages_vouchers_documents_payroll_calculation_health_consumption_and_history(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'role_id' => $this->sellerRole->id,
            'is_active' => true,
            'username' => 'laura_payroll',
        ]);

        $employee = Employee::create([
            'user_id' => $user->id,
            'name' => 'Laura',
            'last_name' => 'Gomez',
            'identification' => '19222333',
            'is_active' => true,
            'total_package_usd' => 280.00,
            'saldo_deuda' => 15.00,
        ]);

        // Sincronizar conceptos salariales y actualizar configuración de paquete
        $settingsResult = $this->repository->updatePayrollSettings($employee, [
            'total_package_usd' => 320.00,
        ]);
        $this->assertIsArray($settingsResult);
        $this->assertEquals(320.00, (float) $employee->fresh()->total_package_usd);

        // Asignar, listar y eliminar voucher salarial
        $voucherStored = $this->repository->storeVoucher($employee, [
            'name' => 'Bono Guardias Nocturnas',
            'type' => 'salary',
            'frequency' => 'monthly',
            'amount' => 35.00,
        ]);
        $this->assertTrue($voucherStored);

        $vouchers = $this->repository->getVouchers($employee);
        $this->assertGreaterThanOrEqual(1, $vouchers->total());

        $nightBonus = UsersSalaryDetails::where('user_id', $user->id)
            ->whereHas('concept', fn ($q) => $q->where('name', 'Bono Guardias Nocturnas'))
            ->firstOrFail();
        $this->assertTrue($this->repository->deleteVoucher($nightBonus));

        // Consumo de salud manual y compra como cliente en farmacia
        $this->repository->setHealthConsumption($employee, 2026, 4, 12.50);
        $this->assertDatabaseHas('employee_health_consumption', [
            'employee_id' => $employee->id,
            'year' => 2026,
            'month' => 4,
            'amount' => 12.50,
        ]);

        $client = Client::create([
            'identification' => '19222333',
            'identification_type' => Client::IDENTIFICATION_TYPE_VENEZOLANO,
            'name' => 'Laura',
            'last_name' => 'Gomez',
        ]);

        Order::create([
            'seller_id' => $user->id,
            'client_id' => $client->id,
            'total_amount' => 20.00,
            'total_amount_usd' => 20.00,
            'money_returns' => 0,
            'status' => Order::COMPLETED,
            'order_date' => '2026-04-12 09:30:00',
            'currency' => 'USD',
        ]);

        // Cálculo de nómina: primera quincena y segunda quincena
        $q1 = $this->repository->runPaymentCalculation($employee->fresh(), [
            'year' => 2026,
            'month' => 4,
            'fortnight' => 1,
        ]);
        $this->assertArrayHasKey('calculation', $q1);
        $this->assertSame(1, $q1['calculation']['fortnight']);

        $q2 = $this->repository->runPaymentCalculation($employee->fresh(), [
            'year' => 2026,
            'month' => 4,
            'fortnight' => 2,
        ]);
        $this->assertArrayHasKey('calculation', $q2);
        $this->assertSame(2, $q2['calculation']['fortnight']);
        $this->assertArrayHasKey('employee', $q2);

        // Historial de pagos desde recibos existentes
        $baseConcept = SalaryConcept::firstOrCreate(
            ['name' => 'Salario Básico Mensual'],
            ['type' => 'salary', 'frequency' => 'monthly']
        );
        $salaryDetail = UsersSalaryDetails::firstOrCreate(
            ['user_id' => $user->id, 'salary_concept_id' => $baseConcept->id],
            ['amount' => 40.00]
        );
        $payslip = Payslip::create([
            'payslip_date' => '2026-04-15',
            'name' => 'NOM-2026-04-Q1',
            'total' => 20.00,
            'status' => 0,
            'exchange_rate' => 45.0,
        ]);
        PayslipDetails::create([
            'payslip_id' => $payslip->id,
            'users_salary_details_id' => $salaryDetail->id,
            'amount' => 20.00,
        ]);

        $paymentsResponse = $this->repository->getPayments($employee->fresh(), []);
        $this->assertArrayHasKey('history', $paymentsResponse);
        $this->assertNotEmpty($paymentsResponse['history']);
        $this->assertSame(2026, $paymentsResponse['history'][0]['year']);

        // Carga y descarga de documentos del empleado
        $storedDocs = $this->repository->storeDocuments($employee->fresh(), [
            'photo' => UploadedFile::fake()->image('avatar.jpg'),
            'cv' => UploadedFile::fake()->create('curriculum.pdf', 32, 'application/pdf'),
        ]);
        $this->assertTrue($storedDocs);

        $download = $this->repository->downloadDocument($employee->fresh(), 'cv');
        $this->assertInstanceOf(StreamedResponse::class, $download);
    }
}
