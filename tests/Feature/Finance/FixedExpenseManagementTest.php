<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\FixedExpenseIntervalUnit;
use App\Enums\FixedExpenseOccurrenceStatus;
use App\Models\ExpenseCategory;
use App\Models\FixedExpense;
use App\Models\FixedExpenseOccurrence;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FixedExpenseManagementTest extends TestCase
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
            'name' => 'Servicios Públicos y Alquiler',
            'is_active' => true,
        ]);
    }

    public function test_can_create_and_list_fixed_expenses(): void
    {
        $payload = [
            'name' => 'Alquiler Sede Principal',
            'category_id' => $this->category->id,
            'amount' => 850.00,
            'currency' => 'USD',
            'interval_unit' => FixedExpenseIntervalUnit::MONTH->value,
            'interval_count' => 1,
            'start_date' => Carbon::now()->startOfMonth()->format('Y-m-d'),
            'reminder_days_before' => 5,
            'notes' => 'Pago mensual al arrendador',
        ];

        $createResponse = $this->postJson('/api/finances/fixed-expenses', $payload);

        $createResponse->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.name', 'Alquiler Sede Principal');
        $this->assertEquals(850.0, (float) $createResponse->json('data.amount'));

        $this->assertDatabaseHas('fixed_expenses', [
            'name' => 'Alquiler Sede Principal',
            'amount' => 850.00,
            'currency' => 'USD',
        ]);

        // Listar gastos fijos
        $listResponse = $this->getJson('/api/finances/fixed-expenses');

        $listResponse->assertOk()
            ->assertJsonPath('status', 'success');

        $items = $listResponse->json('data.data');
        $this->assertNotEmpty($items);
        $this->assertEquals('Alquiler Sede Principal', $items[0]['name']);
    }

    public function test_can_update_toggle_active_and_delete_fixed_expense(): void
    {
        $fixedExpense = FixedExpense::create([
            'name' => 'Internet Fibra Óptica',
            'category_id' => $this->category->id,
            'amount' => 60.00,
            'currency' => 'USD',
            'interval_unit' => FixedExpenseIntervalUnit::MONTH->value,
            'interval_count' => 1,
            'start_date' => Carbon::now()->startOfMonth()->format('Y-m-d'),
            'next_due_date' => Carbon::now()->addMonth()->startOfMonth()->format('Y-m-d'),
            'reminder_days_before' => 3,
            'is_active' => true,
        ]);

        // 1. Actualizar monto
        $updatePayload = [
            'name' => 'Internet Fibra Óptica 300MB',
            'category_id' => $this->category->id,
            'amount' => 75.00,
            'currency' => 'USD',
            'interval_unit' => FixedExpenseIntervalUnit::MONTH->value,
            'interval_count' => 1,
            'start_date' => Carbon::now()->startOfMonth()->format('Y-m-d'),
            'reminder_days_before' => 3,
        ];

        $updateResponse = $this->putJson("/api/finances/fixed-expenses/{$fixedExpense->id}", $updatePayload);
        $updateResponse->assertOk()
            ->assertJsonPath('status', 'success');
        $this->assertEquals(75.0, (float) $updateResponse->json('data.amount'));

        // 2. Toggle Active (Pausar y Reactivar)
        $toggleResponse = $this->patchJson("/api/finances/fixed-expenses/{$fixedExpense->id}/toggle-active");
        $toggleResponse->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertFalse((bool) $fixedExpense->fresh()->is_active);

        // 3. Eliminar (soft delete)
        $deleteResponse = $this->deleteJson("/api/finances/fixed-expenses/{$fixedExpense->id}");
        $deleteResponse->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertSoftDeleted('fixed_expenses', ['id' => $fixedExpense->id]);
    }

    public function test_can_generate_list_summarize_and_skip_occurrences(): void
    {
        // Gasto fijo activo con fecha de inicio hoy para que genere cuota
        $fixedExpense = FixedExpense::create([
            'name' => 'Servicio de Agua',
            'category_id' => $this->category->id,
            'amount' => 35.00,
            'currency' => 'USD',
            'interval_unit' => FixedExpenseIntervalUnit::MONTH->value,
            'interval_count' => 1,
            'start_date' => Carbon::now()->subDays(2)->format('Y-m-d'),
            'next_due_date' => Carbon::now()->format('Y-m-d'),
            'reminder_days_before' => 3,
            'is_active' => true,
        ]);

        // 1. Generar cuotas pendientes
        $generateResponse = $this->postJson('/api/finances/fixed-expenses/occurrences/generate');
        $generateResponse->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('fixed_expense_occurrences', [
            'fixed_expense_id' => $fixedExpense->id,
            'status' => FixedExpenseOccurrenceStatus::PENDING->value,
        ]);

        $occurrence = FixedExpenseOccurrence::where('fixed_expense_id', $fixedExpense->id)->first();
        $this->assertNotNull($occurrence);

        // 2. Consultar listado de cuotas
        $listResponse = $this->getJson('/api/finances/fixed-expenses/occurrences');
        $listResponse->assertOk()
            ->assertJsonPath('status', 'success');

        // 3. Consultar resumen de cuotas por moneda
        $summaryResponse = $this->getJson('/api/finances/fixed-expenses/occurrences/summary');
        $summaryResponse->assertOk()
            ->assertJsonPath('status', 'success');

        // 4. Omitir cuota
        $skipResponse = $this->postJson("/api/finances/fixed-expenses/occurrences/{$occurrence->id}/skip", [
            'note' => 'Omitido por subsidio municipal temporal',
        ]);

        $skipResponse->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', FixedExpenseOccurrenceStatus::SKIPPED->value);

        $this->assertEquals(
            FixedExpenseOccurrenceStatus::SKIPPED->value,
            $occurrence->fresh()->status->value ?? $occurrence->fresh()->status
        );
    }
}
