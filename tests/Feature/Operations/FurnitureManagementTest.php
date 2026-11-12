<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use App\Models\Furniture;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FurnitureManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['id' => 1], [
            'name' => 'Admin',
            'guard_name' => 'web',
            'menu_permissions' => ['*'],
        ]);

        $this->user = User::factory()->create(['role_id' => $role->id]);
        $this->actingAs($this->user);
    }

    public function test_can_create_and_list_furniture(): void
    {
        $payload = [
            'name' => 'Mostrador Principal de Atención',
            'cost' => 1200.00,
            'acquisition_year' => (int) date('Y') - 1,
            'annual_depreciation_rate' => 10.00,
        ];

        $response = $this->postJson('/api/furniture', $payload);

        $response->assertCreated()
            ->assertJsonPath('message', 'Mobiliario creado con éxito.')
            ->assertJsonPath('furniture.name', 'Mostrador Principal de Atención');

        $this->assertDatabaseHas('furnitures', [
            'name' => 'Mostrador Principal de Atención',
            'cost' => 1200.00,
        ]);

        $listResponse = $this->getJson('/api/furniture');
        $listResponse->assertOk()
            ->assertJsonFragment(['name' => 'Mostrador Principal de Atención']);
    }

    public function test_can_update_and_delete_furniture(): void
    {
        $furniture = Furniture::create([
            'name' => 'Estante Metálico #3',
            'cost' => 450.00,
            'acquisition_year' => 2024,
            'annual_depreciation_rate' => 10.00,
        ]);

        $updateResponse = $this->putJson("/api/furniture/{$furniture->id}", [
            'name' => 'Estante Metálico #3 - Modificado',
            'cost' => 500.00,
            'acquisition_year' => 2024,
            'annual_depreciation_rate' => 12.00,
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('furniture.name', 'Estante Metálico #3 - Modificado');

        $this->assertDatabaseHas('furnitures', [
            'id' => $furniture->id,
            'cost' => 500.00,
        ]);

        $deleteResponse = $this->deleteJson("/api/furniture/{$furniture->id}");
        $deleteResponse->assertNoContent();

        $this->assertDatabaseMissing('furnitures', [
            'id' => $furniture->id,
        ]);
    }

    public function test_can_calculate_total_value_and_depreciation(): void
    {
        Furniture::create([
            'name' => 'Nevera Clínica para Vacunas',
            'cost' => 2000.00,
            'acquisition_year' => 2023,
            'annual_depreciation_rate' => 10.00,
        ]);

        $valueResponse = $this->getJson('/api/furniture/value');
        $valueResponse->assertOk()
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonStructure([
                'data' => [
                    'total_value',
                    'currency',
                    'calculated_at',
                ],
            ]);

        $depreciationResponse = $this->getJson('/api/furniture/depreciation');
        $depreciationResponse->assertOk()
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonStructure([
                'data' => [
                    'total_depreciation',
                    'currency',
                    'calculated_at',
                ],
            ]);
    }
}
