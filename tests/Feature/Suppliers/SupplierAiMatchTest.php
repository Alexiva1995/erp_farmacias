<?php

namespace Tests\Feature\Suppliers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupplierAiMatchTest extends TestCase
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
    }

    public function test_accept_and_reject_validate_required_fields(): void
    {
        $this->postJson('/api/supplier-ai-match/accept', [])
            ->assertStatus(422);

        $this->postJson('/api/supplier-ai-match/reject', [])
            ->assertStatus(422);
    }
}
