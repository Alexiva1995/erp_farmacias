<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\ExchangeRate;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FiscalHistory;
use App\Models\Invoice;
use App\Models\IslrDeclaration;
use App\Models\Order;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\TaxUnit;
use App\Models\User;
use App\Services\Islr\IslrQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IslrQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    private IslrQueryService $service;
    private User $user;
    private ExpenseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new IslrQueryService();

        $role = Role::firstOrCreate(
            ['id' => 1],
            ['name' => 'Admin', 'guard_name' => 'web', 'menu_permissions' => ['*']]
        );

        $this->user = User::factory()->create(['role_id' => $role->id]);
        $this->category = ExpenseCategory::firstOrCreate(['id' => 1], ['name' => 'Operativos']);

        ExchangeRate::create(['currency_code' => 'BS', 'rate' => 40.0, 'source' => 'test']);
    }

    public function test_calculates_islr_summary_income_costs_deductions_and_monthly_breakdown(): void
    {
        $year = 2026;

        $order = Order::create([
            'seller_id' => $this->user->id,
            'total_amount' => 1000.00,
            'total_amount_usd' => 25.00,
            'money_returns' => 0,
            'status' => Order::COMPLETED,
            'order_date' => '2026-03-10 10:00:00',
            'currency' => 'BS',
        ]);

        FiscalHistory::create([
            'user_id' => $this->user->id,
            'order_id' => $order->id,
            'business_name' => 'Cliente Test',
            'identification' => 'V-12345678',
            'invoice_date' => '2026-03-10',
            'invoice_number' => '00001001',
            'exempt_amount' => 200.00,
            'taxable_amount' => 689.66,
            'iva_amount' => 110.34,
            'total_amount' => 1000.00,
            'is_queued' => false,
        ]);

        // Gasto deducible aprobado
        Expense::create([
            'name' => 'Alquiler Local',
            'category_id' => $this->category->id,
            'amount' => 150.00,
            'amount_usd' => 3.75,
            'currency' => 'BS',
            'has_invoice' => false,
            'is_deductible' => true,
            'expense_date' => '2026-03-12',
            'user_id' => $this->user->id,
            'status' => Expense::STATUS_APPROVED,
        ]);

        // Gasto costo (con factura y no deducible)
        Expense::create([
            'name' => 'Flete Insumos',
            'category_id' => $this->category->id,
            'amount' => 100.00,
            'amount_usd' => 2.50,
            'currency' => 'BS',
            'has_invoice' => true,
            'is_deductible' => false,
            'expense_date' => '2026-03-15',
            'user_id' => $this->user->id,
            'status' => Expense::STATUS_APPROVED,
        ]);

        // Gasto no deducible (sin factura, no deducible)
        Expense::create([
            'name' => 'Caja Chica Varios',
            'category_id' => $this->category->id,
            'amount' => 50.00,
            'amount_usd' => 1.25,
            'currency' => 'BS',
            'has_invoice' => false,
            'is_deductible' => false,
            'expense_date' => '2026-03-18',
            'user_id' => $this->user->id,
            'status' => Expense::STATUS_APPROVED,
        ]);

        $formalSupplier = Supplier::create(['name' => 'Drogueria Formal', 'rif' => 'J-11111111-1']);
        $informalSupplier = Supplier::create(['name' => 'INFORMAL', 'rif' => 'J-99999999-9']);

        // Factura indexada formal: (200 / 20) * 40 = 400 Bs
        Invoice::create([
            'supplier_id' => $formalSupplier->id,
            'uploaded_by' => $this->user->id,
            'registered_by' => $this->user->id,
            'invoice_number' => 'INV-F1',
            'control_number' => 'CTRL-F1',
            'currency' => 'Bs',
            'is_indexed' => true,
            'exchange_rate' => 20.00,
            'created_invoice_date' => '2026-03-20',
            'received_date' => '2026-03-20',
            'payment_date' => '2026-03-20',
            'exp_date' => '2026-04-20',
            'total_amount' => 200.00,
            'total_usd' => 10.00,
            'status' => 'pending',
            'status_payment' => 0,
        ]);

        // Factura de proveedor INFORMAL (debe excluirse de costos)
        Invoice::create([
            'supplier_id' => $informalSupplier->id,
            'uploaded_by' => $this->user->id,
            'registered_by' => $this->user->id,
            'invoice_number' => 'INV-INF1',
            'control_number' => 'CTRL-INF1',
            'currency' => 'Bs',
            'is_indexed' => false,
            'exchange_rate' => 40.00,
            'created_invoice_date' => '2026-03-21',
            'received_date' => '2026-03-21',
            'payment_date' => '2026-03-21',
            'exp_date' => '2026-04-21',
            'total_amount' => 500.00,
            'total_usd' => 12.50,
            'status' => 'pending',
            'status_payment' => 0,
        ]);

        $summary = $this->service->getSummaryData($year);

        $this->assertEquals(1000.0, $summary['gross_income']);
        $this->assertEquals(150.0, $summary['deductions']);
        $this->assertEquals(50.0, $summary['non_deductible']);
        $this->assertEquals(500.0, $summary['costs']); // 100 gasto + 400 factura indexada
        $this->assertEquals(350.0, $summary['net_income']); // 1000 - 500 - 150
        $this->assertEquals(0.0, $summary['withholdings']);
        $this->assertCount(12, $summary['monthly_breakdown']);

        $marchStats = collect($summary['monthly_breakdown'])->firstWhere('month', 3);
        $this->assertEquals(1000.0, $marchStats['fiscal_total']);
        $this->assertEquals(500.0, $marchStats['costs']);
        $this->assertEquals(150.0, $marchStats['deductions']);
        $this->assertEquals(50.0, $marchStats['non_deductible']);

        $grossDetails = $this->service->getGrossIncomeDetails($year);
        $this->assertEquals(1, $grossDetails['fiscal_count']);
        $this->assertEquals(1000.0, (float) $grossDetails['fiscal_total']);

        $dedDetails = $this->service->getDeductionsDetails($year);
        $this->assertEquals(1, $dedDetails['count']);
        $this->assertEquals(150.0, (float) $dedDetails['total']);
    }

    public function test_queries_tax_units_and_islr_declarations(): void
    {
        $oldTu = TaxUnit::create([
            'value' => 9.00,
            'effective_date' => '2025-01-01',
            'is_active' => false,
            'notes' => 'UT anterior',
        ]);

        $activeTu = TaxUnit::create([
            'value' => 43.00,
            'effective_date' => '2026-01-01',
            'is_active' => true,
            'notes' => 'UT vigente',
        ]);

        $this->assertTrue($this->service->hasActiveTaxUnit());
        $this->assertSame($activeTu->id, $this->service->getActiveTaxUnit()?->id);
        $this->assertCount(2, $this->service->getAllTaxUnits());
        $this->assertSame($oldTu->id, $this->service->getTaxUnitById($oldTu->id)?->id);
        $this->assertCount(1, $this->service->getTaxUnitsByDateRange('2026-01-01', '2026-12-31'));

        $paid = IslrDeclaration::create([
            'year' => 2025,
            'amount' => 2500.00,
            'status' => 'paid',
            'declaration_date' => '2026-03-31',
        ]);
        $paid->forceFill(['created_at' => now()->subDay()])->save();

        $unpaid = IslrDeclaration::create([
            'year' => 2026,
            'amount' => 3100.00,
            'status' => 'unpaid',
            'declaration_date' => '2026-04-15',
        ]);

        $this->assertTrue($this->service->declarationExistsForYear(2025));
        $this->assertFalse($this->service->declarationExistsForYear(2020));
        $this->assertSame($paid->id, $this->service->getDeclarationByYear(2025)?->id);
        $this->assertSame($unpaid->id, $this->service->getLatestDeclaration()?->id);
        $this->assertCount(2, $this->service->getAllDeclarations());
        $this->assertCount(1, $this->service->getPaidDeclarations());
        $this->assertCount(1, $this->service->getUnpaidDeclarations());
    }
}
