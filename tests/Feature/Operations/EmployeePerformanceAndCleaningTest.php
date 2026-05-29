<?php

namespace Tests\Feature\Operations;

use App\Models\CleaningActivity;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeePerformanceAndCleaningTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $employeeUser;
    private Employee $employee;
    private CleaningActivity $activity;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Crear roles necesarios
        $role1 = new \App\Models\Role();
        $role1->id = 1;
        $role1->name = 'Administrador';
        $role1->save();

        $role2 = new \App\Models\Role();
        $role2->id = 2;
        $role2->name = 'Cajero';
        $role2->save();

        // 2. Crear administrador de prueba
        $this->admin = User::create([
            'username' => 'admin_operations',
            'email' => 'adminops@example.com',
            'password_hash' => bcrypt('password'),
            'role_id' => 1,
            'is_active' => true,
        ]);

        // 3. Crear empleado de prueba
        $this->employeeUser = User::create([
            'username' => 'jose_empleado',
            'email' => 'joseemp@example.com',
            'password_hash' => bcrypt('password'),
            'role_id' => 2,
            'is_active' => true,
        ]);

        $this->employee = Employee::create([
            'name' => 'José',
            'last_name' => 'López',
            'identification' => 'V-88888888',
            'user_id' => $this->employeeUser->id,
            'is_active' => true,
        ]);

        // 4. Crear una actividad de limpieza base
        $this->activity = CleaningActivity::create([
            'activity' => 'Limpieza de Estantes de Medicamentos',
            'description' => 'Limpiar y ordenar los estantes del pasillo central',
            'frequency' => 'Diaria',
            'is_active' => true,
        ]);
    }

    /**
     * Test de ciclo de vida de Actividades de Limpieza (CRUD)
     */
    public function test_can_manage_cleaning_activities_crud(): void
    {
        // 1. Crear nueva actividad
        $responseStore = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/cleaning-activities', [
                'activity' => 'Desinfección de Mostrador',
                'description' => 'Desinfectar mostradores de venta',
                'frequency' => 'Diaria',
            ]);

        $responseStore->assertStatus(201)
            ->assertJsonPath('message', 'Actividad creada con éxito.');

        $activityId = $responseStore->json('activity.id');

        // 2. Obtener lista de actividades
        $responseIndex = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/cleaning-activities');

        $responseIndex->assertStatus(200);
        $this->assertGreaterThanOrEqual(2, $responseIndex->json('total'));

        // 3. Actualizar la actividad
        $responseUpdate = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/cleaning-activities/{$activityId}", [
                'activity' => 'Desinfección de Mostrador Principal',
                'description' => 'Desinfectar mostradores y área de POS',
                'frequency' => 'Diaria',
            ]);

        $responseUpdate->assertStatus(200);
        $this->assertDatabaseHas('cleaning_activities', [
            'id' => $activityId,
            'activity' => 'Desinfección de Mostrador Principal',
        ]);

        // 4. Eliminar la actividad
        $responseDestroy = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/cleaning-activities/{$activityId}");

        $responseDestroy->assertStatus(204);
        $this->assertDatabaseMissing('cleaning_activities', [
            'id' => $activityId,
        ]);
    }

    /**
     * Test de asignación y actualización de estado de actividades
     */
    public function test_can_assign_and_update_employee_cleaning_activities(): void
    {
        // 1. Asignar la actividad al empleado
        $responseAssign = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/employee-cleaning-activities', [
                'employee_id' => $this->employee->id,
                'activities' => [
                    [
                        'activity_id' => $this->activity->id,
                        'status' => 'Pendiente',
                        'day_of_week' => 'Lunes'
                    ]
                ],
            ]);

        $responseAssign->assertStatus(200)
            ->assertJsonPath('data.message', 'Actividades asignadas correctamente');

        // 2. Validar que aparece en asignaciones
        $responseAssignments = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/employee-cleaning-activities/assignments');

        $responseAssignments->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, $responseAssignments->json('data.total'));
    }
}
