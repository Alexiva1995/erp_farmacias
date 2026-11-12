<?php

namespace Tests\Feature\POS;

use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TpvZoomProductTest extends TestCase
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

    public function test_can_fetch_tpv_zoom_product(): void
    {
        $product = Product::create([
            'name' => 'Paracetamol 500mg',
            'code' => 'PAR-500',
            'price' => 2.50,
            'cost' => 1.20,
            'stock' => 40,
            'status' => 1,
        ]);

        $this->getJson("/api/tpv/zoom/product?product_id={$product->id}")
            ->assertOk();
    }
}
