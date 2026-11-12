<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\ExchangeRate;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncomeStatementFinancialTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected ExpenseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['id' => 1], [
            'name' => 'Admin',
            'guard_name' => 'web',
            'menu_permissions' => ['*'],
        ]);

        $this->user = User::factory()->create(['role_id' => $role->id]);
        $this->actingAs($this->user, 'sanctum');

        $this->category = ExpenseCategory::create([
            'name' => 'Gastos Operativos Generales',
            'is_active' => true,
        ]);

        ExchangeRate::create([
            'currency_code' => 'BS',
            'rate' => 50.0000,
            'source' => 'BCV',
        ]);
    }

    public function test_can_calculate_income_statement_summary(): void
    {
        $startDate = Carbon::now()->startOfMonth()->format('Y-m-d');
        $endDate = Carbon::now()->endOfMonth()->format('Y-m-d');
        $midDate = Carbon::now()->startOfMonth()->addDays(5)->format('Y-m-d H:i:s');

        // 1. Crear Venta Completada en USD
        Order::create([
            'order_date' => $midDate,
            'status' => Order::COMPLETED,
            'total_amount' => 500.00,
            'total_amount_usd' => 500.00,
            'total_cost' => 300.00,
            'money_returns' => 0.00,
            'currency' => 'USD',
        ]);

        // 2. Crear Gasto Operativo Aprobado
        Expense::create([
            'name' => 'Mantenimiento de Aire Acondicionado',
            'category_id' => $this->category->id,
            'user_id' => $this->user->id,
            'amount' => 50.00,
            'currency' => 'USD',
            'expense_date' => $midDate,
            'status' => Expense::STATUS_APPROVED,
            'count' => Expense::COUNT_EFECTIVO,
            'has_invoice' => false,
            'is_deductible' => false,
        ]);

        // 3. Consultar endpoint index
        $response = $this->getJson("/api/finances/income-statement?start_date={$startDate}&end_date={$endDate}");

        $response->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertEquals(500.00, (float) $data['income']);
        $this->assertEquals(300.00, (float) $data['costs']);
        $this->assertEquals(50.00, (float) $data['expenses']);
        // Utilidad Neta: 500 - 300 - 50 = 150
        $this->assertEquals(150.00, (float) $data['net_profit']);
        // Margen Bruto: ((500 - 300) / 500) * 100 = 40%
        $this->assertEquals(40.00, (float) $data['margin_percentage']);
    }

    public function test_can_get_summary_and_paginated_details(): void
    {
        $today = Carbon::now()->format('Y-m-d');

        Order::create([
            'order_date' => $today,
            'status' => Order::COMPLETED,
            'total_amount' => 200.00,
            'total_amount_usd' => 200.00,
            'total_cost' => 120.00,
            'money_returns' => 0.00,
            'currency' => 'USD',
        ]);

        // 1. Endpoint /income-statement/summary
        $summaryResponse = $this->getJson("/api/finances/income-statement/summary?start_date={$today}&end_date={$today}");
        $summaryResponse->assertOk()
            ->assertJsonPath('success', true);

        $summary = $summaryResponse->json('data');
        $this->assertArrayHasKey('income', $summary);
        $this->assertArrayHasKey('costs', $summary);
        $this->assertArrayHasKey('net_profit', $summary);

        // 2. Endpoint /income-statement/details
        $detailsResponse = $this->getJson("/api/finances/income-statement/details?start_date={$today}&end_date={$today}");
        $detailsResponse->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_can_reset_income_statement_report_date(): void
    {
        $resetResponse = $this->postJson('/api/finances/income-statement/reset');

        $resetResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Estado de resultados reiniciado con éxito. Se tomarán datos a partir de hoy.');

        $this->assertArrayHasKey('reset_date', $resetResponse->json('data'));
    }
}
