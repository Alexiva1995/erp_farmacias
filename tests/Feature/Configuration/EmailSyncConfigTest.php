<?php

namespace Tests\Feature\Configuration;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmailSyncConfigTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(
            ['id' => 1],
            [
                'name' => 'Admin',
                'guard_name' => 'web',
                'menu_permissions' => ['*'],
            ]
        );

        $this->admin = User::factory()->create([
            'role_id' => $role->id,
        ]);

        Sanctum::actingAs($this->admin);

        if (! \Illuminate\Support\Facades\Schema::hasColumn('general_settings', 'gmail_sync_host')) {
            \Illuminate\Support\Facades\Schema::table('general_settings', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->string('gmail_sync_host')->nullable();
                $table->integer('gmail_sync_port')->nullable();
                $table->string('gmail_sync_folder')->nullable();
                $table->boolean('gmail_sync_enabled')->default(false);
                $table->string('gmail_sync_email')->nullable();
                $table->text('gmail_sync_password')->nullable();
            });
        }
    }

    public function test_can_show_email_sync_configuration(): void
    {
        $response = $this->getJson('/api/configuration/email-sync');

        $response->assertSuccessful()
            ->assertJsonStructure([
                'data',
            ]);
    }

    public function test_can_update_email_sync_configuration(): void
    {
        $payload = [
            'email' => 'compras@farmacia.test',
            'app_password' => 'abcd efgh ijkl mnop',
            'imap_host' => 'imap.gmail.com',
            'imap_port' => 993,
            'is_active' => true,
        ];

        $response = $this->postJson('/api/configuration/email-sync', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Configuración de sincronización de Gmail guardada con éxito.')
            ->assertJsonStructure([
                'message',
                'data',
            ]);
    }
}
