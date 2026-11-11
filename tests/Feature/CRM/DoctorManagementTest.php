<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Models\Doctor;
use App\Models\Role;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoctorManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Specialty $specialty;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['id' => 1], [
            'name' => 'Admin',
            'guard_name' => 'web',
            'menu_permissions' => ['*'],
        ]);

        $this->admin = User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $this->specialty = Specialty::firstOrCreate([
            'name' => 'Cardiología',
        ]);

        $this->actingAs($this->admin);
    }

    public function test_can_list_specialties(): void
    {
        Specialty::firstOrCreate(['name' => 'Inmunología Clínica']);

        $response = $this->getJson('/api/crm/specialties');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertNotEmpty($response->json('data'));
    }

    public function test_can_create_doctor(): void
    {
        $payload = [
            'name' => 'Dr. Alejandro Peña',
            'identification' => 'V-15888999',
            'address' => 'Centro Médico Torre A',
            'specialty_id' => $this->specialty->id,
        ];

        $response = $this->postJson('/api/crm/doctors', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.name', 'Dr. Alejandro Peña')
            ->assertJsonPath('data.identification', 'V-15888999');

        $this->assertDatabaseHas('doctors', [
            'name' => 'Dr. Alejandro Peña',
            'identification' => 'V-15888999',
            'specialty_id' => $this->specialty->id,
        ]);
    }

    public function test_can_consult_and_filter_doctors(): void
    {
        $doc1 = Doctor::create([
            'name' => 'Dra. María Colmenares',
            'identification' => 'V-17001002',
            'address' => 'Clínica del Este',
            'specialty_id' => $this->specialty->id,
        ]);

        $doc2 = Doctor::create([
            'name' => 'Dr. Roberto Mendoza',
            'identification' => 'V-18002003',
            'address' => 'Hospital Central',
            'specialty_id' => $this->specialty->id,
        ]);

        // Consultar por id
        $getDocResponse = $this->getJson("/api/crm/doctors/{$doc1->id}");

        $getDocResponse->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.id', $doc1->id)
            ->assertJsonPath('data.name', 'Dra. María Colmenares');

        // Filtrar paginado
        $filterResponse = $this->postJson('/api/crm/doctors/filtrar', [
            'itemsPerPage' => 10,
            'page' => 1,
            'buscardor_filtro' => 'María',
        ]);

        $filterResponse->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $data = $filterResponse->json('data.data');
        $this->assertCount(1, $data);
        $this->assertSame('Dra. María Colmenares', $data[0]['name']);
    }

    public function test_can_edit_doctor(): void
    {
        $doctor = Doctor::create([
            'name' => 'Dr. Juan Silva Original',
            'identification' => 'V-19555666',
            'address' => 'Consultorio 10',
            'specialty_id' => $this->specialty->id,
        ]);

        $newSpecialty = Specialty::firstOrCreate(['name' => 'Medicina Interna']);

        $editPayload = [
            'id' => $doctor->id,
            'name' => 'Dr. Juan Silva Actualizado',
            'identification' => 'V-19555666',
            'address' => 'Consultorio 12 Torre B',
            'specialty_id' => $newSpecialty->id,
        ];

        $response = $this->postJson("/api/crm/doctors/edit/{$doctor->id}", $editPayload);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.name', 'Dr. Juan Silva Actualizado');

        $this->assertDatabaseHas('doctors', [
            'id' => $doctor->id,
            'name' => 'Dr. Juan Silva Actualizado',
            'specialty_id' => $newSpecialty->id,
        ]);
    }
}
