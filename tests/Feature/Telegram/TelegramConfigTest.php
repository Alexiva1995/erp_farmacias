<?php

declare(strict_types=1);

namespace Tests\Feature\Telegram;

use App\Models\TelegramChannel;
use App\Models\TelegramCommand;
use App\Models\TelegramConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TelegramConfigTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::forceCreate([
            'username' => 'testuser',
            'email' => 'test_telegram_' . time() . '@example.com',
            'password_hash' => bcrypt('password'),
            'is_active' => 1,
        ]);
    }

    public function test_can_fetch_module_commands_with_assigned_channel(): void
    {
        $config = TelegramConfig::create([
            'bot_token' => '123456789:TestToken',
            'chat_id' => '-100123456789',
            'is_active' => true,
        ]);

        $channel = TelegramChannel::create([
            'telegram_config_id' => $config->id,
            'name' => 'Canal Test Farmacia',
            'chat_id' => '-100987654321',
            'module' => 'farmacia',
            'is_active' => true,
        ]);

        $command = TelegramCommand::create([
            'module' => 'farmacia',
            'command' => '/pedido',
            'channel_id' => $channel->id,
            'alias' => 'Pedido Inteligente IA',
            'description' => 'Genera pedido automático de reabastecimiento.',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/telegram/commands/farmacia');

        $response->assertStatus(200)
            ->assertJsonFragment([
                'command' => '/pedido',
                'channel_id' => $channel->id,
            ]);
    }

    public function test_can_toggle_telegram_command_status(): void
    {
        $command = TelegramCommand::create([
            'module' => 'generales',
            'command' => '/tasas',
            'alias' => 'Tasas Actualizadas',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->patchJson("/api/telegram/commands/{$command->id}/toggle", [
                'is_active' => false,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('telegram_commands', [
            'id' => $command->id,
            'is_active' => false,
        ]);
    }

    public function test_can_assign_channel_to_command(): void
    {
        $config = TelegramConfig::create([
            'bot_token' => '123456789:TestToken',
            'chat_id' => '-100123456789',
            'is_active' => true,
        ]);

        $channel = TelegramChannel::create([
            'telegram_config_id' => $config->id,
            'name' => 'Canal Alertas',
            'chat_id' => '-100555555555',
            'module' => 'generales',
            'is_active' => true,
        ]);

        $command = TelegramCommand::create([
            'module' => 'generales',
            'command' => '/cierre_individual',
            'alias' => 'Cierre Individual',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/telegram/commands/{$command->id}", [
                'command' => '/cierre_individual',
                'alias' => 'Cierre Individual',
                'channel_id' => $channel->id,
                'is_active' => true,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('telegram_commands', [
            'id' => $command->id,
            'channel_id' => $channel->id,
        ]);
    }
}
