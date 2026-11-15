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
use App\Repositories\PayslipRepository;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayslipRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private PayslipRepository $repository;
    private User $admin;
    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new PayslipRepository();

        $adminRole = Role::firstOrCreate(['id' => 1], ['name' => 'Admin', 'guard_name' => 'web', 'menu_permissions' => ['*']]);
        $sellerRole = Role::firstOrCreate(['id' => 2], ['name' => 'Vendedor', 'guard_name' => 'web', 'menu_permissions' => ['pos']]);

        $this->admin = User::factory()->create(['role_id' => $adminRole->id, 'is_active' => true]);
        $empUser = User::factory()->create(['role_id' => $sellerRole->id, 'is_active' => true, 'username' => 'emp_nomina']);

        $this->employee = Employee::create([
            'user_id' => $empUser->id,
            'name' => 'Laura',
            'last_name' => 'Gomez',
            'identification' => 'V-15222333',
            'is_active' => true,
            'total_package_usd' => 240.00,
            'saldo_deuda' => 10.00,
        ]);

        ExchangeRate::create(['currency_code' => 'BS', 'rate' => 45.0, 'source' => 'test']);
        ExchangeRate::create(['currency_code' => 'COP', 'rate' => 4200.0, 'source' => 'test']);
        ExchangeRate::create(['currency_code' => 'COPS', 'rate' => 4200.0, 'source' => 'test']);
    }

    public function test_generates_eligible_details_updates_finalizes_exports_and_reopens_payslip(): void
    {
        $this->actingAs($this->admin);

        // Cliente con compra en farmacia para Asistencia Social de Salud
        $client = Client::create([
            'identification' => 'V-15222333',
            'identification_type' => Client::IDENTIFICATION_TYPE_VENEZOLANO,
            'name' => 'Laura',
            'last_name' => 'Gomez',
        ]);

        Order::create([
            'seller_id' => $this->admin->id,
            'client_id' => $client->id,
            'total_amount' => 30.00,
            'total_amount_usd' => 30.00,
            'money_returns' => 0,
            'status' => Order::COMPLETED,
            'order_date' => '2026-04-18 10:00:00',
            'currency' => 'USD',
        ]);

        // 1ra quincena (día 15)
        $firstDate = Carbon::parse('2026-04-15');
        $firstDetails = $this->repository->getEligibleSalaryDetails($firstDate);
        $this->assertCount(1, $firstDetails); // solo Salario Básico Mensual (50%)
        $this->assertEquals(20.0, $firstDetails->first()->amount);

        $this->assertTrue($this->repository->generate($firstDate, 'Primera Quincena Abril', $firstDetails));

        // 2da quincena (día 30) - incluye deducciones, bono alimentación, salud y bono rendimiento
        $secondDate = Carbon::parse('2026-04-30');
        $secondDetails = $this->repository->getEligibleSalaryDetails($secondDate);
        $this->assertGreaterThan(1, $secondDetails->count());

        $this->assertTrue($this->repository->generate($secondDate, 'Segunda Quincena Abril', $secondDetails));

        $payslip = Payslip::orderByDesc('id')->first();
        $this->assertNotNull($payslip);

        // Consultar vouchers del empleado
        $vouchersRes = $this->repository->getEmployeeVouchers($payslip, $this->employee);
        $this->assertEquals(45.0, $vouchersRes['currency']);
        $this->assertNotEmpty($vouchersRes['results']);

        // Actualizar detalle con updateDetails()
        $firstVoucherId = $vouchersRes['results'][0]['id'];
        $this->assertTrue($this->repository->updateDetails($payslip, [
            'vouchers' => [['id' => $firstVoucherId, 'amount_usd' => 50.00]],
        ]));
        $this->assertFalse($this->repository->updateDetails($payslip, ['vouchers' => []]));

        // Exportable data & getData (normal y full)
        $dataNormal = $this->repository->getData($payslip->fresh(), 'normal');
        $this->assertEquals('Bs.', $dataNormal['currency_code']);
        $this->assertNotEmpty($dataNormal['items']);

        $dataFull = $this->repository->getData($payslip->fresh(), 'full');
        $this->assertEquals('COP', $dataFull['currency_code']);

        // Finalizar en COP (registra Expense + Transaction y congela total_full_cop en index)
        $this->repository->finalize($payslip->fresh(), [
            'currency' => 'COP',
            'count' => 'Efectivo',
            'payed' => 840000.00,
        ]);

        $this->assertDatabaseHas('expenses', ['name' => 'Nómina', 'currency' => 'COP']);
        $this->assertDatabaseHas('transactions', ['description' => 'Pago de nómina', 'type' => 'CASH']);

        // Index con filtros y cálculo congelado
        $list = $this->repository->index([
            'perPage' => 10,
            'startDate' => '2026-04-01',
            'endDate' => '2026-04-30',
            'status' => 1,
        ]);
        $this->assertEquals(1, $list->total());
        $this->assertEquals(840000.0, $list->first()->total_full_cop);

        // Reabrir y eliminar primera quincena
        $this->assertTrue($this->repository->reopen($payslip->fresh()));
        $this->assertEquals(0, $payslip->fresh()->status);

        $firstPayslip = Payslip::orderBy('id')->first();
        $this->assertTrue($this->repository->destroy($firstPayslip));
    }
}
