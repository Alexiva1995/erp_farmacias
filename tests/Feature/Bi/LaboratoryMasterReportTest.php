<?php

namespace Tests\Feature\Bi;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LaboratoryMasterReportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password_hash' => bcrypt('password'),
            'is_active' => true,
        ]);
    }

    public function test_catalogs_endpoint_returns_data()
    {
        DB::table('groups_laboratories')->insert(['id' => 1, 'name' => 'Grupo Alfa']);
        DB::table('laboratories')->insert(['id' => 1, 'name' => 'Lab 1', 'group_id' => 1]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/bi/laboratories/catalogs?group_by_corporate=true');

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'Grupo Alfa']);
    }

    public function test_dashboard_endpoint_with_group_by_corporate()
    {
        DB::table('groups_laboratories')->insert(['id' => 1, 'name' => 'Grupo Alfa']);
        DB::table('laboratories')->insert(['id' => 1, 'name' => 'Lab 1', 'group_id' => 1]);
        DB::table('products')->insert([
            'id' => 1,
            'name' => 'Producto 1',
            'laboratory_id' => 1,
            'stock' => 10,
            'unit_cost' => 5,
            'sale_price' => 10,
            'is_active' => 1,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/bi/laboratories/dashboard?start_date=2026-01-01&end_date=2026-12-31&group_by_corporate=true');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'rankings' => ['by_units', 'by_revenue', 'by_stock'],
            'trends',
            'stock_on_hand',
            'profitability',
        ]);
    }

    public function test_dashboard_endpoint_without_group_by_corporate()
    {
        DB::table('laboratories')->insert(['id' => 1, 'name' => 'Lab 1']);
        DB::table('products')->insert([
            'id' => 1,
            'name' => 'Producto 1',
            'laboratory_id' => 1,
            'stock' => 10,
            'unit_cost' => 5,
            'sale_price' => 10,
            'is_active' => 1,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/bi/laboratories/dashboard?start_date=2026-01-01&end_date=2026-12-31&group_by_corporate=false');

        $response->assertStatus(200);
    }
}
