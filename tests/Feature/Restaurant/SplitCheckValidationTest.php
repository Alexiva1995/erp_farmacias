<?php

namespace Tests\Feature\Restaurant;

use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SplitCheckValidationTest extends TestCase
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

    public function test_split_check_validates_required_fields(): void
    {
        $order = Order::create([
            'order_date' => now()->toDateString(),
            'status' => 'Pending',
            'total_amount' => 30.00,
            'total_amount_usd' => 30.00,
            'money_returns' => 0.00,
            'currency' => 'USD',
            'seller_id' => $this->admin->id,
        ]);

        $this->postJson("/api/orders/{$order->id}/split", [])
            ->assertStatus(422);
    }
}
