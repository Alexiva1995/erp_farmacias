<?php

declare(strict_types=1);

namespace Tests\Feature\Bi;

use App\Models\Laboratory;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpiryAndSupplierReturnsReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Laboratory $laboratory;
    protected Supplier $supplier;

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

        $this->laboratory = Laboratory::create(['name' => 'Laboratorios Bayer']);
        $this->supplier = Supplier::create([
            'name' => 'Drogueria Alianza',
            'rif' => 'J-30112233-4',
            'credit_days' => 15,
            'is_active' => true,
        ]);
    }

    public function test_can_get_expiry_dashboard_report(): void
    {
        $product = Product::create([
            'name' => 'Aspirina Protect 100mg',
            'laboratory_id' => $this->laboratory->id,
            'unit_cost' => 3.00,
            'sale_price' => 4.50,
            'stock' => 40,
        ]);

        // Lote próximo a vencer en 20 días
        ProductLot::create([
            'product_id' => $product->id,
            'supplier_id' => $this->supplier->id,
            'lot_number' => 'LOT-EXP-20D',
            'expiration_date' => Carbon::now()->addDays(20)->format('Y-m-d'),
            'quantity' => 25,
            'unit_cost' => 3.00,
            'location' => 'ESTANTE-1',
        ]);

        // Lote ya vencido hace 5 días
        ProductLot::create([
            'product_id' => $product->id,
            'supplier_id' => $this->supplier->id,
            'lot_number' => 'LOT-EXP-PAST',
            'expiration_date' => Carbon::now()->subDays(5)->format('Y-m-d'),
            'quantity' => 15,
            'unit_cost' => 3.00,
            'location' => 'CUARENTENA',
        ]);

        $response = $this->getJson('/api/bi/expiry');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_can_get_supplier_returns_90_days_report(): void
    {
        $product = Product::create([
            'name' => 'Cardioaspirina 81mg',
            'laboratory_id' => $this->laboratory->id,
            'unit_cost' => 4.00,
            'sale_price' => 6.00,
            'stock' => 60,
        ]);

        ProductLot::create([
            'product_id' => $product->id,
            'supplier_id' => $this->supplier->id,
            'lot_number' => 'LOT-RET-45D',
            'expiration_date' => Carbon::now()->addDays(45)->format('Y-m-d'),
            'quantity' => 60,
            'unit_cost' => 4.00,
            'location' => 'ALMACEN-PRINCIPAL',
        ]);

        $response = $this->getJson('/api/bi/supplier-returns?days=90');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }
}
