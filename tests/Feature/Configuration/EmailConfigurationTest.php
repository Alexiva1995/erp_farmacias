<?php

declare(strict_types=1);

namespace Tests\Feature\Configuration;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailConfigurationTest extends TestCase
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
        $this->actingAs($this->user, 'sanctum');
    }

    public function test_can_get_and_update_email_configuration(): void
    {
        // 1. Obtener configuración inicial
        $getResponse = $this->getJson('/api/configuration/emails');
        $getResponse->assertOk()
            ->assertJsonPath('success', true);

        // 2. Actualizar configuración SMTP y plantillas
        $payload = [
            'mail_mailer' => 'smtp',
            'mail_host' => 'smtp.mailtrap.io',
            'mail_port' => 587,
            'mail_username' => 'farmacia_erp',
            'mail_password' => 'secret123',
            'mail_encryption' => 'tls',
            'mail_from_address' => 'no-reply@farmacia.com',
            'mail_from_name' => 'Farmacia Central ERP',
            'ecommerce_email_templates' => [
                'order_created' => [
                    'enabled' => true,
                    'subject' => 'Confirmación de Pedido #{{order_id}}',
                    'body' => 'Gracias por su compra.',
                ],
            ],
        ];

        $updateResponse = $this->postJson('/api/configuration/emails', $payload);

        $updateResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Configuración de correos y plantillas guardada con éxito.')
            ->assertJsonPath('data.mail_host', 'smtp.mailtrap.io')
            ->assertJsonPath('data.mail_port', 587)
            ->assertJsonPath('data.mail_from_address', 'no-reply@farmacia.com');

        // 3. Verificar persistencia
        $verifyResponse = $this->getJson('/api/configuration/emails');
        $verifyResponse->assertOk()
            ->assertJsonPath('data.mail_host', 'smtp.mailtrap.io')
            ->assertJsonPath('data.mail_from_name', 'Farmacia Central ERP');
    }

    public function test_validates_email_configuration_payload(): void
    {
        $invalidPayload = [
            'mail_port' => 99999, // Excede 65535
            'mail_encryption' => 'invalid_protocol',
            'mail_from_address' => 'not-an-email',
        ];

        $response = $this->postJson('/api/configuration/emails', $invalidPayload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'mail_port',
                'mail_encryption',
                'mail_from_address',
            ]);
    }
}
