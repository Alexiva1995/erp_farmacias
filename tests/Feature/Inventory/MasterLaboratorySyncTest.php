<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Laboratory;
use App\Models\Product;
use App\Models\ProductSupplier;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Inventory\MasterLaboratorySyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterLaboratorySyncTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected MasterLaboratorySyncService $syncService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesSeeder::class);
        $this->syncService = app(MasterLaboratorySyncService::class);

        $this->user = User::factory()->create([
            'email' => 'admin@sync.test',
            'role_id' => 1,
        ]);
    }

    public function test_sync_laboratories_and_remap_tenant_products(): void
    {
        $generales = Laboratory::create(['name' => 'GENERALES']);
        $bayer = Laboratory::create(['name' => 'BAYER']);
        $pfizer = Laboratory::create(['name' => 'PFIZER']);

        $supplier = Supplier::create([
            'name' => 'Distribuidor Farmacéutico',
            'rif' => 'J-12345678-9',
            'is_active' => true,
            'dispatch_days' => ['monday'],
            'order_days' => ['monday'],
        ]);

        // 1. Producto en GENERALES que coincide con un barcode en product_suppliers de la BD Central
        $product1 = Product::create([
            'name' => 'Aspirina 100mg',
            'barcode' => '7591000000001',
            'laboratory_id' => $generales->id,
            'unit_cost' => 1.00,
            'sale_price' => 1.50,
            'stock' => 10,
            'is_active' => true,
        ]);

        ProductSupplier::create([
            'supplier_id' => $supplier->id,
            'name' => 'Aspirina 100mg Genérica',
            'barcode_match' => '7591000000001',
            'laboratory' => 'BAYER',
            'quantity' => 50,
            'unit_cost' => 0.80,
            'unit_cost_usd' => 0.80,
            'connection_date' => now(),
            'is_active' => true,
        ]);

        // 2. Producto sin laboratorio que coincide por nombre con un producto registrado en Central
        $product2 = Product::create([
            'name' => 'Atorvastatina 20mg',
            'barcode' => '7591000000002',
            'laboratory_id' => null,
            'unit_cost' => 2.00,
            'sale_price' => 3.00,
            'stock' => 5,
            'is_active' => true,
        ]);

        Product::create([
            'name' => 'Atorvastatina 20mg',
            'barcode' => null,
            'laboratory_id' => $pfizer->id,
            'unit_cost' => 2.00,
            'sale_price' => 3.00,
            'stock' => 0,
            'is_active' => true,
        ]);

        $result = $this->syncService->remapTenantProductsLaboratories();

        $this->assertGreaterThanOrEqual(2, $result['remapped']);

        // Verificar reasignaciones correctas
        $product1->refresh();
        $this->assertEquals($bayer->id, $product1->laboratory_id);

        $product2->refresh();
        $this->assertEquals($pfizer->id, $product2->laboratory_id);
    }

    public function test_api_endpoint_sync_master_laboratories(): void
    {
        $generales = Laboratory::create(['name' => 'GENERALES']);
        $roche = Laboratory::create(['name' => 'ROCHE']);

        $supplier = Supplier::create([
            'name' => 'Proveedor Roche Test',
            'rif' => 'J-98765432-1',
            'is_active' => true,
            'dispatch_days' => ['monday'],
            'order_days' => ['monday'],
        ]);

        $product = Product::create([
            'name' => 'Rocephin 1g',
            'barcode' => '7592000000001',
            'laboratory_id' => $generales->id,
            'unit_cost' => 5.00,
            'sale_price' => 7.00,
            'stock' => 20,
            'is_active' => true,
        ]);

        ProductSupplier::create([
            'supplier_id' => $supplier->id,
            'name' => 'Rocephin 1g Ampolla',
            'barcode_match' => '7592000000001',
            'laboratory' => 'ROCHE',
            'quantity' => 10,
            'unit_cost' => 4.50,
            'unit_cost_usd' => 4.50,
            'connection_date' => now(),
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/inventory/laboratories-manage/sync-master');

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'imported_laboratories',
                'total_master_laboratories',
                'remapped_products',
                'remaining_generales',
                'total_evaluated',
            ]);

        $product->refresh();
        $this->assertEquals($roche->id, $product->laboratory_id);
    }

    public function test_artisan_command_sync_laboratories(): void
    {
        Laboratory::create(['name' => 'GENERALES']);

        $exitCode = $this->artisan('drogueria:sync-laboratories')
            ->assertExitCode(0);

        $this->assertEquals(0, $exitCode->run());
    }
}
