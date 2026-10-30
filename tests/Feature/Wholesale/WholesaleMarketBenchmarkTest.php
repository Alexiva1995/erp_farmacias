<?php

declare(strict_types=1);

namespace Tests\Feature\Wholesale;

use App\Models\Laboratory;
use App\Models\Product;
use App\Models\ProductSupplier;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Wholesale\WholesaleMarketBenchmarkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WholesaleMarketBenchmarkTest extends TestCase
{
    use RefreshDatabase;

    protected WholesaleMarketBenchmarkService $benchmarkService;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesSeeder::class);
        $this->benchmarkService = app(WholesaleMarketBenchmarkService::class);

        $this->user = User::factory()->create([
            'email' => 'admin@drogueria.test',
            'role_id' => 1,
        ]);
    }

    protected function createMarketFixtures(): array
    {
        $lab = Laboratory::create(['name' => 'Laboratorio Farma']);

        $supplierCompetitorA = Supplier::create([
            'name' => 'Droguería Competidora Alfa C.A.',
            'rif' => 'J-11111111-1',
            'is_active' => true,
            'dispatch_days' => ['monday'],
            'order_days' => ['monday'],
        ]);

        $supplierCompetitorB = Supplier::create([
            'name' => 'Droguería Competidora Beta C.A.',
            'rif' => 'J-22222222-2',
            'is_active' => true,
            'dispatch_days' => ['tuesday'],
            'order_days' => ['tuesday'],
        ]);

        // 1. Producto Barato (Nuestro precio = 8.00 vs Mercado = 10.00)
        ProductSupplier::create([
            'supplier_id' => $supplierCompetitorA->id,
            'name' => 'Ibuprofeno 400mg Competidor',
            'barcode_match' => '7590000000001',
            'laboratory' => 'Laboratorio Farma',
            'quantity' => 100,
            'unit_cost' => 10.00,
            'unit_cost_usd' => 10.00,
            'connection_date' => now(),
            'is_active' => true,
        ]);

        $productCheaper = Product::create([
            'name' => 'Ibuprofeno 400mg Propio',
            'barcode' => '7590000000001',
            'laboratory_id' => $lab->id,
            'unit_cost' => 6.00,
            'sale_price' => 8.00,
            'stock' => 50,
            'is_active' => true,
        ]);

        // 2. Producto Caro (Nuestro precio = 15.00 vs Mercado = 12.00)
        ProductSupplier::create([
            'supplier_id' => $supplierCompetitorB->id,
            'name' => 'Paracetamol 500mg Competidor',
            'barcode_match' => '7590000000002',
            'laboratory' => 'Laboratorio Farma',
            'quantity' => 80,
            'unit_cost' => 12.00,
            'unit_cost_usd' => 12.00,
            'connection_date' => now(),
            'is_active' => true,
        ]);

        $productExpensive = Product::create([
            'name' => 'Paracetamol 500mg Propio',
            'barcode' => '7590000000002',
            'laboratory_id' => $lab->id,
            'unit_cost' => 11.00,
            'sale_price' => 15.00,
            'stock' => 30,
            'is_active' => true,
        ]);

        // 3. Producto Sin Cobertura por Stock 0 (Creado pero stock 0)
        ProductSupplier::create([
            'supplier_id' => $supplierCompetitorA->id,
            'name' => 'Loratadina 10mg Competidor',
            'barcode_match' => '7590000000003',
            'laboratory' => 'Laboratorio Farma',
            'quantity' => 200,
            'unit_cost' => 4.50,
            'unit_cost_usd' => 4.50,
            'connection_date' => now(),
            'is_active' => true,
        ]);

        $productZeroStock = Product::create([
            'name' => 'Loratadina 10mg Propio',
            'barcode' => '7590000000003',
            'laboratory_id' => $lab->id,
            'unit_cost' => 4.00,
            'sale_price' => 5.00,
            'stock' => 0,
            'is_active' => true,
        ]);

        // 4. Producto Sin Cobertura por No Creado (Existe en mercado pero no en local)
        ProductSupplier::create([
            'supplier_id' => $supplierCompetitorA->id,
            'name' => 'Omeprazol 20mg Competidor',
            'barcode_match' => '7590000000004',
            'laboratory' => 'Laboratorio Farma',
            'quantity' => 150,
            'unit_cost' => 3.00,
            'unit_cost_usd' => 3.00,
            'connection_date' => now(),
            'is_active' => true,
        ]);

        return [
            'cheaper' => $productCheaper,
            'expensive' => $productExpensive,
            'zero_stock' => $productZeroStock,
        ];
    }

    public function test_benchmark_summary_computes_correct_segments_and_metrics(): void
    {
        $this->createMarketFixtures();

        $summary = $this->benchmarkService->getBenchmarkSummary();

        $this->assertEquals(4, $summary['total_market_products']);
        $this->assertEquals(2, $summary['our_active_evaluated_count']); // 1 cheaper + 1 more expensive
        $this->assertEquals(1, $summary['cheaper_count']);
        $this->assertEquals(1, $summary['more_expensive_count']);
        $this->assertEquals(2, $summary['uncovered_count']);
        $this->assertEquals(1, $summary['uncovered_not_created_count']);
        $this->assertEquals(1, $summary['uncovered_zero_stock_count']);
        $this->assertEquals(50.0, $summary['competitiveness_index']);
    }

    public function test_paginated_benchmark_items_filters_by_segment(): void
    {
        $this->createMarketFixtures();

        // Pestaña Más Baratos
        $cheaperItems = $this->benchmarkService->getPaginatedBenchmarkItems('cheaper');
        $this->assertCount(1, $cheaperItems->items());
        $this->assertEquals('7590000000001', $cheaperItems->items()[0]['barcode']);
        $this->assertEquals(8.00, $cheaperItems->items()[0]['our_price_usd']);
        $this->assertEquals(10.00, $cheaperItems->items()[0]['best_market_price_usd']);

        // Pestaña Más Caros
        $expensiveItems = $this->benchmarkService->getPaginatedBenchmarkItems('more_expensive');
        $this->assertCount(1, $expensiveItems->items());
        $this->assertEquals('7590000000002', $expensiveItems->items()[0]['barcode']);
        $this->assertEquals(15.00, $expensiveItems->items()[0]['our_price_usd']);
        $this->assertEquals(12.00, $expensiveItems->items()[0]['best_market_price_usd']);

        // Pestaña Sin Cobertura (No creados)
        $uncoveredNotCreated = $this->benchmarkService->getPaginatedBenchmarkItems('uncovered', ['coverage_type' => 'not_created']);
        $this->assertCount(1, $uncoveredNotCreated->items());
        $this->assertEquals('7590000000004', $uncoveredNotCreated->items()[0]['barcode']);
        $this->assertNull($uncoveredNotCreated->items()[0]['product_id']);

        // Pestaña Sin Cobertura (Agotados en stock 0)
        $uncoveredZeroStock = $this->benchmarkService->getPaginatedBenchmarkItems('uncovered', ['coverage_type' => 'zero_stock']);
        $this->assertCount(1, $uncoveredZeroStock->items());
        $this->assertEquals('7590000000003', $uncoveredZeroStock->items()[0]['barcode']);
        $this->assertEquals(0, $uncoveredZeroStock->items()[0]['our_stock']);
    }

    public function test_api_endpoints_return_anonymous_market_intelligence(): void
    {
        $this->createMarketFixtures();

        $responseSummary = $this->actingAs($this->user)->getJson('/api/wholesale/market-benchmark/summary');
        $responseSummary->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.cheaper_count', 1)
            ->assertJsonPath('data.more_expensive_count', 1)
            ->assertJsonPath('data.uncovered_count', 2);

        $responseItems = $this->actingAs($this->user)->getJson('/api/wholesale/market-benchmark/items?segment=more_expensive');
        $responseItems->assertOk()
            ->assertJsonPath('success', true);

        $jsonPayload = $responseItems->getContent();

        // Validación estricta de anonimato: Prohibido exponer nombres o IDs de competidores
        $this->assertStringNotContainsString('Droguería Competidora', $jsonPayload);
        $this->assertStringNotContainsString('supplier_name', $jsonPayload);
        $this->assertStringNotContainsString('supplier_id', $jsonPayload);
    }

    public function test_can_quick_adjust_product_price_from_benchmark(): void
    {
        $fixtures = $this->createMarketFixtures();
        $expensiveProduct = $fixtures['expensive'];

        $response = $this->actingAs($this->user)->patchJson(
            "/api/wholesale/market-benchmark/products/{$expensiveProduct->id}/price",
            ['price_usd' => 11.90]
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('product_id', $expensiveProduct->id)
            ->assertJsonPath('new_price_usd', 11.90);

        $this->assertDatabaseHas('products', [
            'id' => $expensiveProduct->id,
            'sale_price' => 11.90,
        ]);
    }
}
