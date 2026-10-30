<?php

declare(strict_types=1);

namespace Tests\Feature\Wholesale;

use App\Models\GeneralSetting;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\ProductSupplier;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Import\DrogueriaMasterCatalogImportService;
use Database\Seeders\DrogueriaPresentationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DrogueriaCatalogImportAndSeederTest extends TestCase
{
    use RefreshDatabase;

    protected DrogueriaMasterCatalogImportService $importService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesSeeder::class);
        $this->importService = app(DrogueriaMasterCatalogImportService::class);
    }

    public function test_get_master_suppliers_with_catalog_count(): void
    {
        $supplier = Supplier::create([
            'name' => 'Droguería Vickmedicals C.A.',
            'rif' => 'J-12345678-0',
            'credit_days' => 15,
            'dispatch_days' => ['monday', 'friday'],
            'order_days' => ['monday'],
            'is_active' => true,
        ]);

        ProductSupplier::create([
            'supplier_id' => $supplier->id,
            'name' => 'Amoxicilina 500mg',
            'barcode_match' => '759000111222',
            'laboratory' => 'Calox',
            'quantity' => 50,
            'unit_cost' => 2.50,
            'unit_cost_usd' => 2.50,
            'connection_date' => now(),
            'credit_days' => 15,
            'is_active' => true,
        ]);

        $list = $this->importService->getMasterSuppliersWithCatalogCount();
        $found = collect($list)->firstWhere('id', $supplier->id);

        $this->assertNotNull($found);
        $this->assertEquals('Droguería Vickmedicals C.A.', $found['name']);
        $this->assertEquals(1, $found['catalog_items_count']);
    }

    public function test_import_catalog_to_drogueria_inventory(): void
    {
        $supplier = Supplier::create([
            'name' => 'Droguería Nena C.A.',
            'rif' => 'J-98765432-1',
            'credit_days' => 7,
            'dispatch_days' => ['monday'],
            'order_days' => ['monday'],
            'is_active' => true,
        ]);

        ProductSupplier::create([
            'supplier_id' => $supplier->id,
            'name' => 'Losartán 50mg',
            'barcode_match' => '759888999000',
            'laboratory' => 'Leti',
            'quantity' => 120,
            'unit_cost' => 1.80,
            'unit_cost_usd' => 1.80,
            'connection_date' => now(),
            'credit_days' => 7,
            'expiration' => '2027-12-31',
            'is_active' => true,
        ]);

        $result = $this->importService->importCatalogToDrogueriaInventory($supplier->id, [
            'margin_percentage' => 20.0,
            'default_credit_days' => 7,
            'default_stock_if_zero' => 50,
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['data']['created_products']);
        $this->assertEquals(1, $result['data']['created_lots']);

        // Verificar que el producto se creó con el margen aplicado: 1.80 * 1.20 = 2.16
        $product = Product::where('barcode', '759888999000')->first();
        $this->assertNotNull($product);
        $this->assertEquals(2.16, (float) $product->sale_price);
        $this->assertEquals(7, (int) $product->credit_days);

        // Verificar que el lote FEFO se creó con fecha de expiración
        $lot = ProductLot::where('product_id', $product->id)->first();
        $this->assertNotNull($lot);
        $this->assertEquals(120, (float) $lot->quantity);
    }

    public function test_drogueria_presentation_seeder_runs_cleanly(): void
    {
        $this->seed(DrogueriaPresentationSeeder::class);

        // Verificar configuración vertical
        $this->assertEquals('drogueria', GeneralSetting::first()?->business_type);

        // Verificar usuarios de droguería
        $this->assertDatabaseHas('users', ['email' => 'carlos.asesor@drogueria.com']);
        $this->assertDatabaseHas('users', ['email' => 'pedro.cobranzas@drogueria.com']);

        // Verificar rutas
        $this->assertDatabaseHas('delivery_routes', ['code' => 'RUT-CCS-01']);

        // Verificar farmacias clientes
        $this->assertDatabaseHas('clients', ['identification' => '102938475']);

        // Verificar pedidos en WMS
        $this->assertDatabaseHas('orders', ['credit_term_days' => 0]);
        $this->assertDatabaseHas('orders', ['credit_term_days' => 7]);

        // Verificar abonos y cuentas por cobrar
        $this->assertDatabaseHas('credits', ['credit_type' => 'drogueria_b2b']);
        $this->assertDatabaseHas('credit_payments', ['reference_number' => 'REF-BANESCO-892019']);
    }

    public function test_api_endpoints_for_master_catalog_import(): void
    {
        $admin = User::factory()->create(['role_id' => 1]);
        $this->actingAs($admin);

        $supplier = Supplier::create([
            'name' => 'Cobeca Droguería C.A.',
            'rif' => 'J-11223344-5',
            'credit_days' => 15,
            'dispatch_days' => ['wednesday'],
            'order_days' => ['tuesday'],
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/import/drogueria/master-suppliers');
        $response->assertStatus(200)->assertJsonPath('success', true);

        $previewResponse = $this->getJson("/api/import/drogueria/master-suppliers/{$supplier->id}/preview");
        $previewResponse->assertStatus(200)->assertJsonPath('success', true);
    }
}
