<?php

declare(strict_types=1);

namespace Tests\Feature\Finances;

use App\Models\ExchangeRate;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ExpenseManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private ExpenseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget('resources.all_exchange_rates');

        ExchangeRate::create(['currency_code' => 'USD', 'rate' => 1.0, 'source' => 'test']);
        ExchangeRate::create(['currency_code' => 'BS', 'rate' => 36.0, 'source' => 'test']);
        ExchangeRate::create(['currency_code' => 'COP', 'rate' => 4000.0, 'source' => 'test']);

        $role = Role::firstOrCreate(['id' => 1], [
            'name' => 'Admin',
            'guard_name' => 'web',
            'menu_permissions' => ['*'],
        ]);

        $this->admin = User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $this->category = ExpenseCategory::firstOrCreate(
            ['id' => 1],
            ['name' => 'Servicios Básicos']
        );

        $this->actingAs($this->admin);
    }

    public function test_can_list_and_create_expense_categories(): void
    {
        $response = $this->getJson('/api/finances/expenses/category');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $createResponse = $this->postJson('/api/finances/expenses/category', [
            'name' => 'Mantenimiento y Reparaciones',
        ]);

        $createResponse->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.name', 'Mantenimiento y Reparaciones');

        $this->assertDatabaseHas('expense_categories', [
            'name' => 'Mantenimiento y Reparaciones',
        ]);
    }

    public function test_can_create_normal_expense(): void
    {
        $payload = [
            'name' => 'Pago de Electricidad Local Central',
            'category_id' => $this->category->id,
            'total_amount' => 150.00,
            'total_usd' => 150.00,
            'currency' => 'USD',
            'expense_date' => now()->format('Y-m-d'),
            'user_id' => $this->admin->id,
            'count' => Expense::COUNT_EFECTIVO,
            'has_invoice' => false,
            'is_deductible' => false,
            'iva' => false,
            'exempt_amount' => 0,
            'taxable_base' => 0,
            'tax_amount' => 0,
            'exchange_rate' => 1.0,
        ];

        $response = $this->postJson('/api/finances/expenses/create-normal', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.name', 'Pago de Electricidad Local Central');

        $this->assertDatabaseHas('expenses', [
            'name' => 'Pago de Electricidad Local Central',
            'category_id' => $this->category->id,
            'currency' => 'USD',
        ]);
    }

    public function test_can_filter_and_paginate_expenses(): void
    {
        Expense::create([
            'name' => 'Gasto Operativo Alfa',
            'category_id' => $this->category->id,
            'amount' => 50.0,
            'total_usd' => 50.0,
            'currency' => 'USD',
            'expense_date' => now()->format('Y-m-d'),
            'user_id' => $this->admin->id,
            'count' => Expense::COUNT_EFECTIVO,
            'status' => Expense::STATUS_PENDING,
        ]);

        Expense::create([
            'name' => 'Gasto Operativo Beta',
            'category_id' => $this->category->id,
            'amount' => 75.0,
            'total_usd' => 75.0,
            'currency' => 'USD',
            'expense_date' => now()->format('Y-m-d'),
            'user_id' => $this->admin->id,
            'count' => Expense::COUNT_TRANSFERENCIA,
            'status' => Expense::STATUS_APPROVED,
        ]);

        $response = $this->postJson('/api/finances/expenses/filter-paginate', [
            'itemsPerPage' => 10,
            'page' => 1,
            'buscardor_filtro' => 'Alfa',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertSame('Gasto Operativo Alfa', $data[0]['name']);
    }

    public function test_can_get_global_expense_statistics(): void
    {
        Expense::create([
            'name' => 'Gasto Aprobado 1',
            'category_id' => $this->category->id,
            'amount' => 200.0,
            'total_usd' => 200.0,
            'currency' => 'USD',
            'expense_date' => now()->format('Y-m-d'),
            'user_id' => $this->admin->id,
            'count' => Expense::COUNT_EFECTIVO,
            'status' => Expense::STATUS_APPROVED,
        ]);

        Expense::create([
            'name' => 'Gasto Pendiente 1',
            'category_id' => $this->category->id,
            'amount' => 80.0,
            'total_usd' => 80.0,
            'currency' => 'USD',
            'expense_date' => now()->format('Y-m-d'),
            'user_id' => $this->admin->id,
            'count' => Expense::COUNT_EFECTIVO,
            'status' => Expense::STATUS_PENDING,
        ]);

        $response = $this->postJson('/api/finances/expenses/stats', [
            'fechaDesde_filtro' => now()->subDay()->format('Y-m-d'),
            'fechaHasta_filtro' => now()->addDay()->format('Y-m-d'),
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.totalApproved', 1)
            ->assertJsonPath('data.amountApproved', 200)
            ->assertJsonPath('data.totalPending', 1)
            ->assertJsonPath('data.amountPending', 80);
    }

    public function test_admin_can_change_expense_status(): void
    {
        $expense = Expense::create([
            'name' => 'Gasto Por Aprobar',
            'category_id' => $this->category->id,
            'amount' => 120.0,
            'total_usd' => 120.0,
            'currency' => 'USD',
            'expense_date' => now()->format('Y-m-d'),
            'user_id' => $this->admin->id,
            'count' => Expense::COUNT_EFECTIVO,
            'status' => Expense::STATUS_PENDING,
        ]);

        $response = $this->postJson('/api/finances/expenses/change-status', [
            'id' => $expense->id,
            'status' => Expense::STATUS_APPROVED,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'status' => Expense::STATUS_APPROVED,
        ]);
    }
}
