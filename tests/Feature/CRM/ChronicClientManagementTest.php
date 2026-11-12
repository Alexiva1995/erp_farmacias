<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Models\Client;
use App\Models\Employee;
use App\Models\Laboratory;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChronicClientManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Laboratory $laboratory;

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

        $this->laboratory = Laboratory::create(['name' => 'Laboratorios Calox']);
    }

    public function test_can_update_product_consumption_configuration(): void
    {
        $product = Product::create([
            'name' => 'Losartan Potasico 50mg x 30 Tab',
            'laboratory_id' => $this->laboratory->id,
            'unit_cost' => 1.50,
            'sale_price' => 2.20,
            'stock' => 120,
            'is_deleted' => false,
        ]);

        $payload = [
            'consumption_type' => 'chronic',
            'treatment_duration_days' => 30,
        ];

        $response = $this->putJson("/api/crm/chronic-clients/products-config/{$product->id}", $payload);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.consumption_type', 'chronic')
            ->assertJsonPath('data.treatment_duration_days', 30);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'consumption_type' => 'chronic',
            'is_chronic' => 1,
            'treatment_duration_days' => 30,
        ]);
    }

    public function test_can_remove_invalid_phone_from_client(): void
    {
        $client = Client::create([
            'name' => 'Maria',
            'last_name' => 'Perez',
            'identification' => 'V-15888999',
            'phone' => '+58 412 0000000',
            'email' => 'maria.perez@example.com',
            'is_active' => true,
        ]);

        $response = $this->deleteJson("/api/crm/chronic-clients/remove-phone/{$client->id}");

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.client_id', $client->id)
            ->assertJsonPath('data.old_phone', '+58 412 0000000');

        $this->assertNull($client->fresh()->phone);
    }

    public function test_can_get_daily_fidelity_quotas_matrix(): void
    {
        // Crear un empleado con usuario vinculado e identification requerida
        Employee::create([
            'name' => 'Carlos',
            'last_name' => 'Operador',
            'identification' => 'V-22334455',
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;

        $response = $this->getJson("/api/crm/chronic-clients/daily-quotas-matrix?month={$currentMonth}&year={$currentYear}");

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.month', $currentMonth)
            ->assertJsonPath('data.year', $currentYear)
            ->assertJsonPath('data.daily_quota', 5);

        $this->assertArrayHasKey('employees', $response->json('data'));
        $this->assertArrayHasKey('data', $response->json('data'));
        $this->assertArrayHasKey('summary', $response->json('data'));
    }

    public function test_can_check_client_availability_when_no_previous_orders(): void
    {
        $client = Client::create([
            'name' => 'Jose',
            'last_name' => 'Gomez',
            'identification' => 'V-18777666',
            'phone' => '04141234567',
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/crm/chronic-clients/check-availability/{$client->id}");

        $response->assertOk()
            ->assertJsonPath('status', 'success');

        $data = $response->json('data');
        $this->assertArrayHasKey('available', $data);
        $this->assertArrayHasKey('message', $data);
    }
}
