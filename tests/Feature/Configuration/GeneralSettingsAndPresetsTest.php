<?php

declare(strict_types=1);

namespace Tests\Feature\Configuration;

use App\Models\GeneralSetting;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeneralSettingsAndPresetsTest extends TestCase
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

    public function test_can_get_general_settings(): void
    {
        GeneralSetting::updateOrCreate(
            ['id' => 1],
            [
                'app_name' => 'Farmacia Salud Plus',
                'app_rif' => 'J-30495821-0',
                'fiscal_mode' => 'demo',
                'default_currency' => 'USD',
                'business_type' => 'pharmacy',
            ]
        );

        $response = $this->getJson('/api/general-settings');

        $response->assertOk()
            ->assertJsonPath('data.app_name', 'Farmacia Salud Plus')
            ->assertJsonPath('data.default_currency', 'USD');
    }

    public function test_can_update_general_settings(): void
    {
        $payload = [
            'app_name' => 'Farmacia La Esperanza',
            'app_rif' => 'J-12345678-0',
            'fiscal_mode' => 'demo',
            'default_currency' => 'USD',
            'special_taxpayer_status' => 'activa',
            'blind_cash_closure' => true,
        ];

        $response = $this->postJson('/api/general-settings', $payload);

        $response->assertOk()
            ->assertJsonPath('message', 'Configuración guardada correctamente')
            ->assertJsonPath('data.app_name', 'Farmacia La Esperanza');

        $this->assertDatabaseHas('general_settings', [
            'id' => 1,
            'app_name' => 'Farmacia La Esperanza',
            'blind_cash_closure' => 1,
        ]);
    }

    public function test_can_list_available_presets(): void
    {
        $response = $this->getJson('/api/general-settings/presets');

        $response->assertOk()
            ->assertJsonStructure(['data']);

        $data = $response->json('data');
        $keys = collect($data)->pluck('key')->toArray();
        $this->assertContains('pharmacy', $keys);
        $this->assertContains('restaurant', $keys);
        $this->assertContains('drogueria', $keys);
    }

    public function test_can_apply_vertical_preset_preserving_identity(): void
    {
        GeneralSetting::updateOrCreate(
            ['id' => 1],
            [
                'app_name' => 'Mi Negocio Corporativo',
                'app_rif' => 'J-99999999-9',
                'business_type' => 'pharmacy',
            ]
        );

        $payload = [
            'vertical' => 'restaurant',
            'preserve_identity' => true,
        ];

        $response = $this->postJson('/api/general-settings/apply-preset', $payload);

        $response->assertOk()
            ->assertJsonPath('message', 'Plantilla de vertical aplicada exitosamente');

        $this->assertDatabaseHas('general_settings', [
            'id' => 1,
            'app_name' => 'Mi Negocio Corporativo',
            'business_type' => 'restaurant',
        ]);
    }
}
