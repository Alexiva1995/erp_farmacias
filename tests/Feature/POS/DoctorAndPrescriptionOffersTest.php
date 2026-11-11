<?php

declare(strict_types=1);

namespace Tests\Feature\POS;

use App\Models\Doctor;
use App\Models\DoctorOffer;
use App\Models\PrescriptionOffer;
use App\Models\Role;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoctorAndPrescriptionOffersTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Doctor $doctor;

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

        $specialty = Specialty::firstOrCreate(['name' => 'Pediatría']);

        $this->doctor = Doctor::create([
            'name' => 'Dra. Gabriela Torres',
            'identification' => 'V-21333444',
            'specialty_id' => $specialty->id,
        ]);

        $this->actingAs($this->admin);
    }

    public function test_can_create_and_list_doctor_offers(): void
    {
        $payload = [
            'doctor_id' => $this->doctor->id,
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addDays(30)->format('Y-m-d'),
            'is_active' => true,
            'discount' => 15.00,
        ];

        $storeResponse = $this->postJson('/api/tpv/promotions/doctor-offer', $payload);

        $storeResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.doctor_id', $this->doctor->id);

        $this->assertEquals(15.00, $storeResponse->json('data.discount'));

        $this->assertDatabaseHas('doctor_offers', [
            'doctor_id' => $this->doctor->id,
            'discount' => 15.00,
            'is_active' => 1,
        ]);

        $listResponse = $this->getJson('/api/tpv/promotions/doctor-offer');

        $listResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('total', 1);

        $this->assertCount(1, $listResponse->json('data'));
    }

    public function test_can_update_doctor_offer(): void
    {
        $offer = DoctorOffer::create([
            'doctor_id' => $this->doctor->id,
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addDays(20)->format('Y-m-d'),
            'is_active' => true,
            'discount' => 10.00,
        ]);

        $updatePayload = [
            'doctor_id' => $this->doctor->id,
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addDays(40)->format('Y-m-d'),
            'is_active' => true,
            'discount' => 20.00,
        ];

        $response = $this->putJson("/api/tpv/promotions/doctor-offer/{$offer->id}", $updatePayload);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertEquals(20.00, $response->json('data.discount'));

        $this->assertDatabaseHas('doctor_offers', [
            'id' => $offer->id,
            'discount' => 20.00,
        ]);
    }

    public function test_can_create_and_list_prescription_offers(): void
    {
        $payload = [
            'name' => 'Plan Receta Salud Infantil',
            'discount_percentage' => 12.50,
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addDays(15)->format('Y-m-d'),
            'is_active' => true,
        ];

        $storeResponse = $this->postJson('/api/tpv/promotions/prescription-offer', $payload);

        $storeResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Plan Receta Salud Infantil');

        $this->assertDatabaseHas('prescription_offers', [
            'name' => 'Plan Receta Salud Infantil',
            'discount_percentage' => 12.50,
        ]);

        $listResponse = $this->getJson('/api/tpv/promotions/prescription-offer');

        $listResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('total', 1);

        $this->assertCount(1, $listResponse->json('data'));
    }
}
