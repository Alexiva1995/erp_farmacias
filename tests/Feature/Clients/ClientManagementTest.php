<?php

declare(strict_types=1);

namespace Tests\Feature\Clients;

use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['id' => 1], [
            'name' => 'Admin',
            'guard_name' => 'web',
            'menu_permissions' => ['*'],
        ]);

        $this->user = User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $this->actingAs($this->user);
    }

    public function test_can_create_individual_client(): void
    {
        $payload = [
            'name' => 'Carlos',
            'last_name' => 'Perez',
            'identification_type' => Client::IDENTIFICATION_TYPE_VENEZOLANO,
            'identification' => '20123456',
            'phone' => '04147329185',
            'address' => 'Av. Bolívar, Local 12',
            'is_spe' => false,
        ];

        $response = $this->postJson('/api/crm/clients', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'message' => 'Cliente creado exitosamente',
        ]);

        $this->assertDatabaseHas('clients', [
            'name' => 'Carlos',
            'last_name' => 'Perez',
            'identification' => '20123456',
            'identification_type' => 'V-',
            'phone' => '04147329185',
        ]);
    }

    public function test_can_query_client_by_identification(): void
    {
        $client = Client::create([
            'name' => 'Maria',
            'last_name' => 'Gomez',
            'identification_type' => Client::IDENTIFICATION_TYPE_VENEZOLANO,
            'identification' => '25987654',
            'phone' => '04249876543',
            'address' => 'Calle Sucre #45',
        ]);

        $response = $this->getJson("/api/crm/clients/identification/{$client->identification}");

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'data' => [
                'identification' => '25987654',
                'name' => 'Maria',
            ],
        ]);
    }

    public function test_can_edit_client_information(): void
    {
        $client = Client::create([
            'name' => 'Luis',
            'last_name' => 'Hernandez',
            'identification_type' => Client::IDENTIFICATION_TYPE_VENEZOLANO,
            'identification' => '18456789',
            'phone' => '04126381942',
            'address' => 'Sector Las Acacias',
            'is_spe' => false,
        ]);

        $editPayload = [
            'id' => $client->id,
            'name' => 'Luis Alberto',
            'last_name' => 'Hernandez Silva',
            'identification_type' => Client::IDENTIFICATION_TYPE_VENEZOLANO,
            'identification' => '18456789',
            'phone' => '04126381942',
            'address' => 'Sector Las Acacias, Edif. Los Pinos',
            'is_spe' => false,
        ];

        $response = $this->postJson("/api/crm/clients/edit/{$client->id}", $editPayload);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'message' => 'Cliente editado exitosamente',
        ]);

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'name' => 'Luis Alberto',
            'last_name' => 'Hernandez Silva',
            'address' => 'Sector Las Acacias, Edif. Los Pinos',
        ]);
    }

    public function test_can_filter_and_paginate_clients(): void
    {
        Client::create([
            'name' => 'Pedro',
            'last_name' => 'Ramirez',
            'identification_type' => Client::IDENTIFICATION_TYPE_VENEZOLANO,
            'identification' => '19111222',
            'phone' => '04149998877',
            'address' => 'Urbanización del Este',
        ]);

        Client::create([
            'name' => 'Farmacia Los Altos C.A.',
            'identification_type' => Client::IDENTIFICATION_TYPE_JURIDICO,
            'identification' => '30111222',
            'phone' => '02129998877',
            'address' => 'C.C. Los Altos Local 5',
        ]);

        $filterPayload = [
            'page' => 1,
            'itemsPerPage' => 10,
            'buscardor_filtro' => 'Ramirez',
        ];

        $response = $this->postJson('/api/crm/clients/filtrar', $filterPayload);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
        ]);

        $data = $response->json('data.data');
        $this->assertNotEmpty($data);
        $this->assertEquals('19111222', $data[0]['identification']);
    }

    public function test_can_delete_client(): void
    {
        $client = Client::create([
            'name' => 'Cliente Temporal',
            'identification_type' => Client::IDENTIFICATION_TYPE_VENEZOLANO,
            'identification' => '15333444',
            'phone' => '04165554433',
            'address' => 'Dirección de prueba',
        ]);

        $response = $this->deleteJson("/api/crm/clients/{$client->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'message' => 'Cliente eliminado exitosamente',
        ]);

        $this->assertSoftDeleted('clients', [
            'id' => $client->id,
        ]);
    }
}
