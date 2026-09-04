<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Laboratory;
use App\Models\Origin;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\Role;
use App\Models\User;
use App\Services\Catalog\MasterCatalogClientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExternalCatalogImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['id' => 1], ['name' => 'Admin']);
        $this->user = User::factory()->create([
            'role_id' => 1,
            'is_active' => true,
        ]);
        Sanctum::actingAs($this->user);
    }

    public function test_can_import_external_catalog_from_csv_and_calculates_sales_average(): void
    {
        // Mock de MasterCatalogClientService
        $mockMaster = $this->createMock(MasterCatalogClientService::class);
        $mockMaster->method('lookupBulk')->willReturnCallback(function (array $barcodes) {
            $res = [];
            foreach ($barcodes as $b) {
                if ($b === '7703712035256') {
                    $res[$b] = [
                        'name' => 'ACICLOVIR 800MG OFICIAL MASTER',
                        'laboratory_id' => 1,
                        'category_id' => 1,
                    ];
                }
            }
            return $res;
        });
        $this->app->instance(MasterCatalogClientService::class, $mockMaster);

        $category = Category::factory()->create();
        $laboratory = Laboratory::create(['name' => 'Coaspharma']);

        // CSV con dos productos: uno que existe en master y otro nuevo
        $csvContent = "PRD_CODIGO;PRD_REFERENCIA;PRD_DESCRIPCION;EIN_EXISTENCIA;TPC_COSTOACTUAL;DIM_EXENTO;EIN_EXISTENCIADIFERIDA\n" .
                      "09748;7703712035256;ACICLOVIR LOCAL;35;9.11;G;120\n" .
                      "02466;17593255000418;GASA ESTERIL 4x4;100;4.17;E;36\n";

        $file = UploadedFile::fake()->createWithContent('inventario.csv', $csvContent);

        $response = $this->postJson('/api/import-external-catalog', [
            'file' => $file,
            'cutoff_date' => '2026-06-30', // Mes 6 -> 120 / 6 = 20 ventas promedio
            'is_initial_load' => '1',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total_rows' => 2,
                    'created' => 2,
                    'matched_with_master' => 1,
                ],
            ]);

        // Verificar primer producto (homologado con Master)
        $p1 = Product::where('barcode', '7703712035256')->first();
        $this->assertNotNull($p1);
        $this->assertEquals('ACICLOVIR 800MG OFICIAL MASTER', $p1->name);
        $this->assertEquals(35, $p1->stock);
        $this->assertEquals(9.11, (float) $p1->unit_cost);
        $this->assertTrue((bool) $p1->iva);
        $this->assertEquals(20.0, (float) $p1->sales_average); // 120 / 6

        // Verificar lote del primer producto
        $lot1 = ProductLot::where('product_id', $p1->id)->first();
        $this->assertNotNull($lot1);
        $this->assertEquals(35, $lot1->quantity);
        $this->assertEquals(9.11, (float) $lot1->unit_cost);

        // Verificar segundo producto (nombre del archivo normalizado en mayúsculas)
        $p2 = Product::where('barcode', '17593255000418')->first();
        $this->assertNotNull($p2);
        $this->assertEquals('GASA ESTERIL 4X4', $p2->name);
        $this->assertEquals(100, $p2->stock);
        $this->assertFalse((bool) $p2->iva);
        $this->assertEquals(6.0, (float) $p2->sales_average); // 36 / 6
    }

    public function test_updates_existing_product_stock_and_recalculates_sales_delta(): void
    {
        $mockMaster = $this->createMock(MasterCatalogClientService::class);
        $mockMaster->method('lookupBulk')->willReturn([]);
        $this->app->instance(MasterCatalogClientService::class, $mockMaster);

        $category = Category::factory()->create();

        // Producto preexistente con venta acumulada previa
        $product = Product::create([
            'name' => 'Producto Prueba',
            'barcode' => '7591234567890',
            'category_id' => $category->id,
            'stock' => 10,
            'unit_cost' => 5.0,
            'sale_price' => 5.0,
            'sales_average' => 10.0,
            'external_accumulated_sales' => 50.0,
            'external_sales_date' => '2026-05-31',
            'is_active' => 1,
            'is_deleted' => 0,
        ]);

        ProductLot::create([
            'product_id' => $product->id,
            'lot_number' => 'LOT-INICIAL',
            'expiration_date' => '2028-12-31',
            'quantity' => 10,
            'unit_cost' => 5.0,
            'amount_usd' => 50.0,
        ]);

        // Nuevo archivo con acumulado 70 (delta = 20)
        $csvContent = "PRD_CODIGO;PRD_REFERENCIA;PRD_DESCRIPCION;EIN_EXISTENCIA;TPC_COSTOACTUAL;DIM_EXENTO;EIN_EXISTENCIADIFERIDA\n" .
                      "001;7591234567890;Producto Prueba Actualizado;25;5.50;E;70\n";

        $file = UploadedFile::fake()->createWithContent('update.csv', $csvContent);

        $response = $this->postJson('/api/import-external-catalog', [
            'file' => $file,
            'cutoff_date' => '2026-06-30',
            'is_initial_load' => '0', // Carga periódica
        ]);

        $response->assertStatus(200);

        $product->refresh();
        $this->assertEquals(25, $product->stock);
        $this->assertEquals(5.50, (float) $product->unit_cost);
        $this->assertEquals(70.0, (float) $product->external_accumulated_sales);
        // (delta: 20 + anterior_avg: 10) / 2 = 15
        $this->assertEquals(15.0, (float) $product->sales_average);

        $lot = ProductLot::where('product_id', $product->id)->first();
        $this->assertEquals(25, $lot->quantity);
        $this->assertEquals(5.50, (float) $lot->unit_cost);
    }

    public function test_rejects_invalid_file_format(): void
    {
        $file = UploadedFile::fake()->create('invalid.pdf', 100, 'application/pdf');

        $response = $this->postJson('/api/import-external-catalog', [
            'file' => $file,
            'cutoff_date' => '2026-06-30',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }
}
