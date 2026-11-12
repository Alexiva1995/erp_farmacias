<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Models\BookingVisit;
use App\Models\Court;
use App\Models\FixedSchedule;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class FixedScheduleAndVisitTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Court $court;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $role = Role::firstOrCreate(['id' => 1], [
            'name' => 'Admin',
            'guard_name' => 'web',
            'menu_permissions' => ['*'],
        ]);

        $this->user = User::factory()->create(['role_id' => $role->id]);
        $this->actingAs($this->user, 'sanctum');

        $this->court = Court::create([
            'name' => 'Cancha Principal Techada',
            'price' => 35.00,
        ]);
    }

    public function test_can_create_update_and_delete_fixed_schedule(): void
    {
        // 1. Crear horario fijo válido
        $payload = [
            'court_id' => $this->court->id,
            'day_of_week' => 1, // Lunes
            'start_time' => '18:00',
            'end_time' => '19:00',
            'client_name' => 'Liga Juvenil FC',
            'client_whatsapp' => '+584121112233',
        ];

        $createResponse = $this->postJson('/api/fixed-schedules', $payload);
        $createResponse->assertCreated()
            ->assertJsonPath('message', 'Horario fijo configurado correctamente.')
            ->assertJsonPath('data.client_name', 'Liga Juvenil FC');

        $scheduleId = (int) $createResponse->json('data.id');

        // 2. Actualizar horario fijo sin conflicto
        $updatePayload = [
            'court_id' => $this->court->id,
            'day_of_week' => 1,
            'start_time' => '19:00',
            'end_time' => '20:00',
            'client_name' => 'Liga Juvenil FC - Modificado',
            'client_whatsapp' => '+584121112233',
        ];

        $updateResponse = $this->putJson("/api/fixed-schedules/{$scheduleId}", $updatePayload);
        $updateResponse->assertOk()
            ->assertJsonPath('message', 'Horario fijo actualizado correctamente.')
            ->assertJsonPath('data.client_name', 'Liga Juvenil FC - Modificado');

        // 3. Eliminar horario fijo
        $deleteResponse = $this->deleteJson("/api/fixed-schedules/{$scheduleId}");
        $deleteResponse->assertOk()
            ->assertJsonPath('message', 'Horario fijo eliminado correctamente de todas las semanas.');

        $this->assertDatabaseMissing('fixed_schedules', ['id' => $scheduleId]);
    }

    public function test_prevents_fixed_schedule_overlapping_conflicts(): void
    {
        // Crear horario existente de 18:00 a 19:00 el día Lunes (day_of_week = 1)
        FixedSchedule::create([
            'court_id' => $this->court->id,
            'day_of_week' => 1,
            'start_time' => '18:00',
            'end_time' => '19:00',
            'client_name' => 'Equipo Alfa',
            'client_whatsapp' => '+584145556677',
        ]);

        // Intentar registrar un horario que solapa de 18:30 a 19:30 el mismo día
        $conflictPayload = [
            'court_id' => $this->court->id,
            'day_of_week' => 1,
            'start_time' => '18:30',
            'end_time' => '19:30',
            'client_name' => 'Equipo Beta',
            'client_whatsapp' => '+584149998877',
        ];

        $conflictResponse = $this->postJson('/api/fixed-schedules', $conflictPayload);
        $conflictResponse->assertStatus(422)
            ->assertJsonPath('message', 'Este horario fijo choca con otro horario fijo ya registrado para ese día.');

        $this->assertDatabaseMissing('fixed_schedules', ['client_name' => 'Equipo Beta']);
    }

    public function test_can_register_public_booking_visit_with_anti_duplicate_cooldown(): void
    {
        $ip = '192.168.1.105';

        // 1. Primera visita registra en base de datos
        $firstResponse = $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/public/visits');

        $firstResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Visita registrada.');

        $this->assertDatabaseHas('booking_visits', [
            'ip_address' => $ip,
        ]);
        $this->assertEquals(1, BookingVisit::where('ip_address', $ip)->count());

        // 2. Segunda visita inmediata desde la misma IP no debe duplicar registro
        $secondResponse = $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/public/visits');

        $secondResponse->assertOk()
            ->assertJsonPath('success', true);

        $this->assertEquals(1, BookingVisit::where('ip_address', $ip)->count());
    }
}
