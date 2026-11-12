<?php

declare(strict_types=1);

namespace Tests\Feature\Import;

use App\Models\Product;
use App\Models\ProductLot;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DrogueriaCatalogImportTest extends TestCase
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
    }

    public function test_can_get_master_suppliers_with_catalog_count(): void
    {
        // 1. Crear proveedores en la base central
        $supplierA = Supplier::create([
            'name' => 'Drogueria Nena C.A.',
            'social_reason' => 'Drogueria Nena Compania Anonima',
            'rif' => 'J-12345678-0',
            'credit_days' => 21,
            'is_active' => true,
        ]);

        $supplierB = Supplier::create([
            'name' => 'Drogueria Cobeca',
            'social_reason' => 'Cobeca C.A.',
            'rif' => 'J-87654321-0',
            'credit_days' => 15,
            'is_active' => true,
        ]);

        // 2. Insertar catálogo en product_suppliers
        DB::table('product_suppliers')->insert([
            [
                'supplier_id' => $supplierA->id,
                'name' => 'Atorvastatina 20mg x 30 Tab',
                'barcode_match' => '7591234567890',
                'laboratory' => 'Calox',
                'quantity' => 150,
                'unit_cost' => 2.50,
                'unit_cost_usd' => 2.50,
                'connection_date' => now()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'supplier_id' => $supplierA->id,
                'name' => 'Losartan Potasico 50mg x 30 Tab',
                'barcode_match' => '7599876543210',
                'laboratory' => 'Genven',
                'quantity' => 80,
                'unit_cost' => 1.80,
                'unit_cost_usd' => 1.80,
                'connection_date' => now()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/import/drogueria/master-suppliers');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertNotEmpty($data);

        $nena = collect($data)->firstWhere('id', $supplierA->id);
        $this->assertNotNull($nena);
        $this->assertEquals('Drogueria Nena C.A.', $nena['name']);
        $this->assertEquals(2, $nena['catalog_items_count']);

        $cobeca = collect($data)->firstWhere('id', $supplierB->id);
        $this->assertNotNull($cobeca);
        $this->assertEquals(0, $cobeca['catalog_items_count']);
    }

    public function test_can_preview_supplier_catalog(): void
    {
        $supplier = Supplier::create([
            'name' => 'Drogueria Farvenca',
            'social_reason' => 'Farvenca S.A.',
            'rif' => 'J-11223344-5',
            'credit_days' => 30,
            'is_active' => true,
        ]);

        DB::table('product_suppliers')->insert([
            [
                'supplier_id' => $supplier->id,
                'name' => 'Amoxicilina 500mg Caps',
                'barcode_match' => '7595555444333',
                'laboratory' => 'Leti',
                'quantity' => 45,
                'unit_cost' => 3.20,
                'unit_cost_usd' => 3.20,
                'connection_date' => now()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/import/drogueria/master-suppliers/{$supplier->id}/preview?limit=10");

        $response->assertOk()
            ->assertJsonPath('supplier.name', 'Drogueria Farvenca')
            ->assertJsonPath('metrics.total_items', 1)
            ->assertJsonPath('metrics.with_stock_count', 1);

        $previewItems = $response->json('preview_items');
        $this->assertCount(1, $previewItems);
        $this->assertEquals('Amoxicilina 500mg Caps', $previewItems[0]['name']);
    }

    public function test_can_execute_catalog_import_into_drogueria_inventory(): void
    {
        $supplier = Supplier::create([
            'name' => 'Drogueria Del Sur',
            'rif' => 'J-99887766-1',
            'credit_days' => 14,
            'is_active' => true,
        ]);

        DB::table('product_suppliers')->insert([
            [
                'supplier_id' => $supplier->id,
                'name' => 'Omeprazol 20mg x 28 Cap',
                'barcode_match' => '7590001112223',
                'laboratory' => 'Vargas',
                'quantity' => 200,
                'unit_cost' => 1.50,
                'unit_cost_usd' => 1.50,
                'connection_date' => now()->toDateString(),
                'expiration' => Carbon::now()->addYear()->format('Y-m-d'),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $payload = [
            'master_supplier_id' => $supplier->id,
            'margin_percentage' => 20.0, // 1.50 * 1.20 = 1.80
            'default_credit_days' => 28,
            'default_stock_if_zero' => 50,
            'only_with_stock' => true,
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/import/drogueria/execute', $payload);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.created_products', 1)
            ->assertJsonPath('data.created_lots', 1)
            ->assertJsonPath('data.total_units_loaded', 200);

        // Verificar que el producto fue creado en inventario
        $product = Product::where('barcode', '7590001112223')->first();
        $this->assertNotNull($product);
        $this->assertEquals('OMEPRAZOL 20MG X 28 CAP', $product->name);
        $this->assertEquals(1.50, (float) $product->unit_cost);
        $this->assertEquals(1.80, (float) $product->sale_price);
        $this->assertEquals(200, (float) $product->stock);

        // Verificar creación del lote
        $lot = ProductLot::where('product_id', $product->id)->first();
        $this->assertNotNull($lot);
        $this->assertEquals(200, (float) $lot->quantity);
        $this->assertEquals(1.50, (float) $lot->unit_cost);
        $this->assertEquals('ALMACEN-CENTRAL', $lot->location);
    }
}
