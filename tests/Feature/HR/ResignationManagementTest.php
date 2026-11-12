<?php

namespace Tests\Feature\HR;

use App\Models\Employee;
use App\Models\Resignation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ResignationManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(
            ['id' => 1],
            [
                'name' => 'Admin',
                'guard_name' => 'web',
                'menu_permissions' => ['*'],
            ]
        );

        $this->admin = User::factory()->create([
            'role_id' => $role->id,
        ]);

        Sanctum::actingAs($this->admin);
    }

    public function test_can_list_resignations_and_get_stats(): void
    {
        $employee = Employee::create([
            'user_id' => $this->admin->id,
            'name' => 'Carlos',
            'last_name' => 'Perez',
            'identification' => 'V-12345678',
            'is_active' => true,
        ]);

        Resignation::create([
            'employee_id' => $employee->id,
            'employee_name' => $employee->name,
            'employee_identification' => $employee->identification,
            'resignation_type' => 'voluntary',
            'reason' => 'Motivos personales',
            'start_date' => now()->subYear()->toDateString(),
            'request_date' => now()->toDateString(),
            'effective_date' => now()->addDays(15)->toDateString(),
            'status' => 'pending',
        ]);

        $listResponse = $this->getJson('/api/rrhh/resignations');
        $listResponse->assertStatus(200);

        $statsResponse = $this->getJson('/api/rrhh/resignations/stats');
        $statsResponse->assertStatus(200);
    }

    public function test_can_toggle_employee_status_and_delete_resignation(): void
    {
        $employee = Employee::create([
            'user_id' => $this->admin->id,
            'name' => 'Maria',
            'last_name' => 'Gomez',
            'identification' => 'V-87654321',
            'is_active' => true,
        ]);

        $resignation = Resignation::create([
            'employee_id' => $employee->id,
            'employee_name' => $employee->name,
            'employee_identification' => $employee->identification,
            'resignation_type' => 'voluntary',
            'reason' => 'Cambio de residencia',
            'start_date' => now()->subYear()->toDateString(),
            'request_date' => now()->toDateString(),
            'effective_date' => now()->addDays(10)->toDateString(),
            'status' => 'pending',
        ]);

        $toggleResponse = $this->putJson('/api/rrhh/resignations/toggle-employee-status', [
            'employee_id' => $employee->id,
            'is_active' => false,
        ]);

        $toggleResponse->assertStatus(200);

        $deleteResponse = $this->deleteJson("/api/rrhh/resignations/{$resignation->id}");
        $deleteResponse->assertStatus(200);
    }
}
