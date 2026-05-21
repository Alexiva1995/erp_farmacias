<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceSecurityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test que valida que las rutas financieras den 401 si no hay token de autenticación.
     */
    public function test_finance_routes_require_authentication(): void
    {
        $response = $this->getJson('/api/finances/income-statement');
        $response->assertStatus(401);
    }

    /**
     * Test que valida que las rutas financieras respondan exitosamente si hay un usuario autenticado.
     */
    public function test_finance_routes_allow_authenticated_users(): void
    {
        $user = User::create([
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password_hash' => bcrypt('password'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/finances/income-statement?start_date=2026-01-01&end_date=2026-12-31');

        $response->assertStatus(200);
    }
}
