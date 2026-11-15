<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Product;
use App\Models\ProductLot;
use App\Models\Supplier;
use App\Services\Lots\LotActionService;
use App\Services\Lots\LotQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LotServicesTest extends TestCase
{
    use RefreshDatabase;

    private LotActionService $actionService;
    private LotQueryService $queryService;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actionService = new LotActionService();
        $this->queryService = new LotQueryService();

        $this->product = Product::factory()->create([
            'name' => 'Acetaminofen 500mg',
            'active_ingredient' => 'Paracetamol',
            'stock' => 100,
            'unit_cost' => 1.50,
            'sale_price' => 3.00,
            'lotification_completed' => false,
            'is_active' => true,
            'is_deleted' => false,
        ]);
    }

    public function test_lot_action_service_create_update_delete_and_batch(): void
    {
        // Validación al crear con cantidad 0
        try {
            $this->actionService->createLot([
                'product_id' => $this->product->id,
                'lot_number' => 'LOT-ERR',
                'quantity' => 0,
                'expiration_date' => '2027-01-01',
            ]);
            $this->fail('Se esperaba ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('quantity', $e->errors());
        }

        $lot = $this->actionService->createLot([
            'product_id' => $this->product->id,
            'lot_number' => 'LOT-001',
            'quantity' => 20,
            'expiration_date' => '2027-06-01',
            'unit_cost' => 1.40,
            'location' => 'A-1',
        ]);
        $this->assertEquals(20, (int) $lot->quantity);

        // Exceder el stock sincronizado por Observer debe lanzar ValidationException
        try {
            $this->actionService->updateLot($lot, ['quantity' => 30]);
            $this->fail('Se esperaba ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('quantity', $e->errors());
        }

        $updated = $this->actionService->updateLot($lot, ['quantity' => 15, 'location' => 'A-2']);
        $this->assertEquals(15, (int) $updated->quantity);

        $this->actionService->deleteLot($updated);
        $this->assertEquals(0, (int) $updated->fresh()->quantity);

        // batchUpdateLots: producto inexistente
        $notFound = $this->actionService->batchUpdateLots(['product_id' => 999999, 'lots' => []]);
        $this->assertArrayHasKey('errors', $notFound);

        // batchUpdateLots: validación de lote inválida
        $invalidBatch = $this->actionService->batchUpdateLots([
            'product_id' => $this->product->id,
            'lots' => [
                ['id' => 0, 'lot_number' => '', 'quantity' => 0, 'expiration_date' => 'invalid'],
                ['id' => 0, 'lot_number' => '', 'quantity' => 5, 'expiration_date' => 'invalid'],
            ],
        ]);
        $this->assertArrayHasKey('errors', $invalidBatch);

        // batchUpdateLots: crear, actualizar y marcar para eliminación (qty=0)
        $lotToKeep = ProductLot::create([
            'product_id' => $this->product->id,
            'lot_number' => 'LOT-002',
            'quantity' => 10,
            'expiration_date' => '2027-08-01',
            'unit_cost' => 1.60,
            'location' => 'B-1',
        ]);

        $res = $this->actionService->batchUpdateLots([
            'product_id' => $this->product->id,
            'lots' => [
                ['id' => $lot->id, 'lot_number' => 'LOT-001', 'quantity' => 0, 'expiration_date' => '2027-06-01'],
                ['id' => $lotToKeep->id, 'lot_number' => 'LOT-002-UPD', 'quantity' => 25, 'expiration_date' => '2027-09-01'],
                ['id' => 0, 'lot_number' => 'LOT-003-NEW', 'quantity' => 15, 'expiration_date' => '2028-01-01'],
            ],
        ]);

        $this->assertTrue($res['success']);
        $this->assertTrue((bool) $this->product->fresh()->lotification_completed);
        $this->assertDatabaseHas('product_lots', ['lot_number' => 'LOT-002-UPD', 'quantity' => 25]);
        $this->assertDatabaseHas('product_lots', ['lot_number' => 'LOT-003-NEW', 'quantity' => 15]);
    }

    public function test_lot_action_service_delete_zero_qty_skips_single_lot_product(): void
    {
        $singleProduct = Product::factory()->create(['name' => 'Producto Lote Unico', 'stock' => 0]);
        ProductLot::create([
            'product_id' => $singleProduct->id,
            'lot_number' => 'LOT-SINGLE',
            'quantity' => 0,
            'expiration_date' => '2027-01-01',
        ]);

        $deleted = $this->actionService->deleteLotsWithZeroQuantity();
        $this->assertEquals(0, $deleted);
        $this->assertDatabaseHas('product_lots', ['lot_number' => 'LOT-SINGLE']);
    }

    public function test_lot_query_service_filters_and_sorting(): void
    {
        $supplier = Supplier::create(['name' => 'Drogueria Proveedora', 'rif' => 'J-22222222-2']);

        ProductLot::create([
            'product_id' => $this->product->id,
            'supplier_id' => $supplier->id,
            'lot_number' => 'Q-LOT-1',
            'quantity' => 15,
            'expiration_date' => '2027-05-01',
            'unit_cost' => 2.00,
            'location' => '',
        ]);

        $req = new Request([
            'productId' => $this->product->id,
            'search' => 'Paracetamol',
            'hasStock' => 'true',
            'startDate' => '2026-01-01',
            'endDate' => '2028-12-31',
        ]);

        $this->assertCount(1, $this->queryService->getFilteredQuery($req)->get());

        foreach (['product.name', 'supplier.name', 'quantity', 'expiration_date', 'created_at', 'unit_cost', 'id'] as $sort) {
            $sorted = $this->queryService->getFilteredQuery(new Request(['sortBy' => $sort, 'orderBy' => 'asc']))->get();
            $this->assertNotEmpty($sorted);
        }

        $inconsistent = $this->queryService->getProductsWithInconsistentStockQuery(new Request(['search' => 'Q-LOT']))->get();
        $this->assertCount(1, $inconsistent);

        $noLoc = $this->queryService->getLotsWithoutLocationQuery(new Request(['search' => 'Q-LOT']))->get();
        $this->assertCount(1, $noLoc);

        $this->assertNotEmpty($this->queryService->getProductsWithoutLot());
        $this->assertCount(1, $this->queryService->getAvailableSuppliers());
    }
}
