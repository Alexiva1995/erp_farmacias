<?php

namespace Tests\Unit\Services;

use App\Models\Category;
use App\Models\GroupsProduct;
use App\Models\InventoryMovement;
use App\Models\Laboratory;
use App\Models\Origin;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\Role;
use App\Models\User;
use App\Services\Products\ProductActionService;
use App\Services\Products\ProductQueryService;
use App\Services\Products\ProductStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProductServicesTest extends TestCase
{
    use RefreshDatabase;

    private ProductActionService $actionService;
    private ProductQueryService $queryService;
    private ProductStatsService $statsService;

    protected function setUp(): void
    {
        parent::setUp();

        // Resolvemos los servicios desde el contenedor para probar su integración real
        $this->actionService = app(ProductActionService::class);
        $this->queryService = app(ProductQueryService::class);
        $this->statsService = app(ProductStatsService::class);
    }

    /**
     * Crea un usuario autenticado y catálogos base para las pruebas de productos.
     */
    private function seedCatalogDependencies(): array
    {
        $role = Role::create([
            'name' => 'admin',
            'display_name' => 'Administrador',
        ]);

        $user = User::create([
            'username' => 'product_tester',
            'email' => 'product_tester@example.com',
            'password_hash' => Hash::make('password'),
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $this->actingAs($user);

        $category = Category::create(['name' => 'Analgésicos']);
        $laboratory = Laboratory::create(['name' => 'Lab Central']);
        $origin = Origin::create(['name' => 'Nacional']);
        $group = GroupsProduct::create(['name' => 'Grupo Dolor']);

        return compact('user', 'category', 'laboratory', 'origin', 'group');
    }

    public function test_product_action_service_covers_crud_group_scarce_ignore_and_merge(): void
    {
        $deps = $this->seedCatalogDependencies();

        // Crear producto principal con stock inicial para verificar generación de lote y movimiento
        $product = $this->actionService->createProduct([
            'name' => 'Acetaminofen 500mg Caja x 20',
            'barcode' => '7590001112223',
            'unit_cost' => 1.50,
            'purchase_cost' => 1.50,
            'units_per_inner_pack' => 1,
            'sale_price' => 2.80,
            'category_id' => $deps['category']->id,
            'laboratory_id' => $deps['laboratory']->id,
            'origin_id' => $deps['origin']->id,
            'initial_stock' => 15,
            'stock' => 15,
        ]);

        $this->assertInstanceOf(Product::class, $product);
        $this->assertSame('ACETAMINOFEN 500MG CAJA X 20', $product->name);

        $this->assertDatabaseHas('product_lots', [
            'product_id' => $product->id,
            'lot_number' => 'LOTE-INICIAL',
            'quantity' => 15,
        ]);

        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product->id,
            'movement_type' => 'adjustment',
        ]);

        // Actualizar producto y campos incompletos
        $updated = $this->actionService->updateProduct($product, [
            'name' => 'Acetaminofen 500mg Actualizado',
            'barcode' => '7590001112224',
            'unit_cost' => 1.60,
            'purchase_cost' => 1.60,
            'units_per_inner_pack' => 1,
            'sale_price' => 3.10,
            'category_id' => $deps['category']->id,
            'laboratory_id' => $deps['laboratory']->id,
            'origin_id' => $deps['origin']->id,
        ]);

        $this->assertSame('ACETAMINOFEN 500MG ACTUALIZADO', $updated->name);

        $fieldsUpdated = $this->actionService->updateIncompleteFields($updated, [
            'barcode' => '7590001119999',
            'laboratory_id' => $deps['laboratory']->id,
            'origin_id' => $deps['origin']->id,
        ]);
        $this->assertTrue($fieldsUpdated);

        // Asignar y desasignar de un grupo de productos
        $this->assertTrue($this->actionService->updateProductGroup($updated, $deps['group']->id));
        $this->assertSame($deps['group']->id, $updated->fresh()->group_id);

        $this->assertTrue($this->actionService->unassignFromGroup($updated));
        $this->assertNull($updated->fresh()->group_id);

        // Alternar escasez e ignorar producto temporalmente
        $scarceProduct = $this->actionService->toggleScarceProduct($updated);
        $this->assertTrue((bool) $scarceProduct->is_scarce);

        $ignoredProduct = $this->actionService->ignoreProduct($scarceProduct, 7);
        $this->assertNotNull($ignoredProduct->ignore_until);

        $clearedCount = $this->actionService->clearIgnoredProducts();
        $this->assertGreaterThanOrEqual(1, $clearedCount);
        $this->assertNull($scarceProduct->fresh()->ignore_until);

        // Eliminar lógicamente y restaurar
        $this->actionService->deleteProduct($updated);
        $this->assertNull(Product::find($updated->id));

        $restored = $this->actionService->restoreProduct($updated->id);
        $this->assertFalse((bool) $restored->is_deleted);
        $this->assertNotNull(Product::find($updated->id));

        // Crear un segundo producto y fusionarlo hacia el primero
        $duplicate = $this->actionService->createProduct([
            'name' => 'Acetaminofen Duplicado Merge',
            'barcode' => '7590008887771',
            'unit_cost' => 1.60,
            'purchase_cost' => 1.60,
            'units_per_inner_pack' => 1,
            'sale_price' => 3.10,
            'category_id' => $deps['category']->id,
            'laboratory_id' => $deps['laboratory']->id,
            'origin_id' => $deps['origin']->id,
            'initial_stock' => 5,
            'stock' => 5,
        ]);

        // Validar rechazo si keepProductId no coincide con ninguno de los dos IDs
        $invalidMerge = false;
        try {
            $this->actionService->mergeProducts($restored->id, $duplicate->id, 999999);
        } catch (\Exception $e) {
            $invalidMerge = true;
        }
        $this->assertTrue($invalidMerge);

        $mergeResult = $this->actionService->mergeProducts($restored->id, $duplicate->id, $restored->id);
        $this->assertTrue($mergeResult['success']);
        $this->assertSame($restored->id, $mergeResult['merged_product_id']);
        $this->assertSame($duplicate->id, $mergeResult['deleted_product_id']);

        $this->assertSame(2, ProductLot::where('product_id', $restored->id)->count());
    }

    public function test_product_query_and_stats_services_cover_filters_inventory_value_and_stats(): void
    {
        $deps = $this->seedCatalogDependencies();

        $completeProduct = $this->actionService->createProduct([
            'name' => 'Ibuprofeno 400mg Blister',
            'barcode' => '7591112223334',
            'unit_cost' => 2.00,
            'purchase_cost' => 2.00,
            'units_per_inner_pack' => 1,
            'sale_price' => 4.00,
            'category_id' => $deps['category']->id,
            'laboratory_id' => $deps['laboratory']->id,
            'origin_id' => $deps['origin']->id,
            'group_id' => $deps['group']->id,
            'initial_stock' => 10,
            'stock' => 10,
        ]);

        // Producto incompleto sin código de barras, laboratorio, origen ni grupo
        $incompleteProduct = $this->actionService->createProduct([
            'name' => 'Producto Incompleto Sin Datos',
            'barcode' => null,
            'unit_cost' => 3.00,
            'purchase_cost' => 3.00,
            'units_per_inner_pack' => 1,
            'sale_price' => 6.00,
            'category_id' => $deps['category']->id,
            'laboratory_id' => null,
            'origin_id' => null,
            'initial_stock' => 2,
            'stock' => 2,
        ]);

        // Búsqueda por código de barras
        $foundBarcode = $this->queryService->searchBarcodeProduct(new Request([
            'barcode' => '7591112223334',
        ]));
        $this->assertNotNull($foundBarcode);
        $this->assertSame($completeProduct->id, $foundBarcode->id);

        // Listado filtrado sin búsqueda estricta (compatible con SQLite)
        $filtered = $this->queryService->getFilteredQuery(new Request([
            'q' => 'Ibuprofeno',
            'isStrictSearch' => false,
            'categoryId' => $deps['category']->id,
            'laboratoryId' => $deps['laboratory']->id,
            'originId' => $deps['origin']->id,
            'groupId' => $deps['group']->id,
            'hasStock' => 'true',
            'sortBy' => 'name',
            'orderBy' => 'asc',
        ]))->get();

        $this->assertCount(1, $filtered);
        $this->assertSame($completeProduct->id, $filtered->first()->id);

        // Consultas especializadas de completitud y agrupación
        $this->assertGreaterThanOrEqual(
            1,
            $this->queryService->getIncompleteProductsQuery(new Request())->count()
        );
        $this->assertGreaterThanOrEqual(
            1,
            $this->queryService->getProductsWithoutLaboratoryQuery(new Request())->count()
        );
        $this->assertGreaterThanOrEqual(
            1,
            $this->queryService->getProductsWithoutOriginQuery(new Request())->count()
        );
        $this->assertGreaterThanOrEqual(
            1,
            $this->queryService->getProductsWithoutGroupQuery(new Request())->count()
        );

        // Valor total de inventario: (10 * 2.00) + (2 * 3.00) = 26.00
        $inventoryValue = $this->queryService->calculateInventoryValue();
        $this->assertEqualsWithDelta(26.00, $inventoryValue, 0.01);

        // Consulta de rentabilidad filtrada
        $profitabilityRows = $this->queryService->getFilteredQueryForProfitability(new Request([
            'q' => 'Ibuprofeno',
            'isStrictSearch' => false,
        ]))->get();
        $this->assertGreaterThanOrEqual(1, $profitabilityRows->count());

        // Estadísticas de ventas del producto
        $stats = $this->statsService->getProductSalesStats($completeProduct);
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_units_sold', $stats);
        $this->assertEquals(0.0, $stats['total_units_sold']);
        $this->assertInstanceOf(InventoryMovement::class, InventoryMovement::first());
    }
}
