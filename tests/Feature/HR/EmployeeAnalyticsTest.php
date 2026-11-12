<?php

namespace Tests\Feature\HR;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmployeeAnalyticsTest extends TestCase
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

    public function test_can_fetch_employee_analytics_dashboard_compare_and_detail(): void
    {
        $employee = Employee::create([
            'user_id' => $this->admin->id,
            'name' => 'Ana',
            'last_name' => 'Lopez',
            'identification' => 'V-11223344',
            'is_active' => true,
        ]);

        $today = now()->toDateString();

        $this->getJson("/api/bi/employees/dashboard?start_date={$today}&end_date={$today}")
            ->assertOk();

        $this->getJson("/api/bi/employees/compare?employee_a={$employee->id}&employee_b={$employee->id}&start_date={$today}&end_date={$today}")
            ->assertOk();

        $this->getJson("/api/bi/employees/{$employee->id}/detail?start_date={$today}&end_date={$today}")
            ->assertOk();
    }
}
