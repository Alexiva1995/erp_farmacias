<?php

declare(strict_types=1);

namespace Tests\Feature\Configuration;

use App\Models\Role;
use App\Models\TelegramChannel;
use App\Models\TelegramConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TelegramIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

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

        $this->actingAs($this->admin);
    }

    public function test_can_get_and_update_telegram_config(): void
    {
        $getResponse = $this->getJson('/api/telegram/config');

        $getResponse->assertStatus(200)
            ->assertJsonStructure(['data', 'channels']);

        $updatePayload = [
            'bot_token' => '123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ',
            'chat_id' => '-1001234567890',
            'admin_chat_id' => '-1009876543210',
            'is_active' => true,
        ];

        $updateResponse = $this->putJson('/api/telegram/config', $updatePayload);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('message', 'Configuración de Telegram actualizada con éxito.')
            ->assertJsonPath('data.chat_id', '-1001234567890')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('telegram_configs', [
            'id' => 1,
            'chat_id' => '-1001234567890',
            'is_active' => 1,
        ]);
    }

    public function test_can_manage_telegram_channels(): void
    {
        $payload = [
            'name' => 'Canal de Alertas de Stock',
            'chat_id' => '-100555666777',
            'module' => 'inventario',
            'description' => 'Canal dedicado a avisos de quiebres de stock y lotes vencidos',
            'is_active' => true,
        ];

        $storeResponse = $this->postJson('/api/telegram/channels', $payload);

        $storeResponse->assertStatus(201)
            ->assertJsonPath('message', 'Canal de Telegram registrado con éxito.')
            ->assertJsonPath('data.name', 'Canal de Alertas de Stock');

        $channelId = $storeResponse->json('data.id');

        $this->assertDatabaseHas('telegram_channels', [
            'id' => $channelId,
            'name' => 'Canal de Alertas de Stock',
            'module' => 'inventario',
        ]);

        $toggleResponse = $this->patchJson("/api/telegram/channels/{$channelId}/toggle", [
            'is_active' => false,
        ]);

        $toggleResponse->assertStatus(200)
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('telegram_channels', [
            'id' => $channelId,
            'is_active' => 0,
        ]);
    }
}
