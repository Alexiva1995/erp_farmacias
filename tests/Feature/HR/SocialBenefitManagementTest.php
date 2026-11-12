<?php

namespace Tests\Feature\HR;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SocialBenefitManagementTest extends TestCase
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

    public function test_can_list_employees_and_get_settlement_data(): void
    {
        $employee = Employee::create([
            'user_id' => $this->admin->id,
            'name' => 'Luis',
            'last_name' => 'Martinez',
            'identification' => 'V-19283746',
            'is_active' => true,
        ]);

        $this->getJson('/api/rrhh/social-benefits/employees')
            ->assertOk();

        $this->getJson("/api/rrhh/social-benefits/employees/{$employee->id}/settlement-data")
            ->assertOk();
    }
}
