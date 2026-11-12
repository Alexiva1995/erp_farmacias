<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Laboratory;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductAbcAnalysisReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Laboratory $laboratory;

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

        $this->laboratory = Laboratory::create(['name' => 'Laboratorio Genéricos']);
    }

    public function test_can_generate_abc_analysis_with_pareto_classification(): void
    {
        // 1. Producto A: ventas de $850 (73.9% de participación -> Clase A)
        $productA = Product::create([
            'name' => 'Atorvastatina 20mg',
            'laboratory_id' => $this->laboratory->id,
            'stock' => 100,
            'sales_average' => 25.0,
            'unit_cost' => 5.0,
            'sale_price' => 8.5,
        ]);

        // 2. Producto B: ventas de $200 (17.4% -> acumula 91.3% -> Clase B)
        $productB = Product::create([
            'name' => 'Cetirizina 10mg',
            'laboratory_id' => $this->laboratory->id,
            'stock' => 50,
            'sales_average' => 15.0,
            'unit_cost' => 2.0,
            'sale_price' => 4.0,
        ]);

        // 3. Producto C: ventas de $100 (8.7% -> acumula 100% -> Clase C)
        $productC = Product::create([
            'name' => 'Vitamina C 1g',
            'laboratory_id' => $this->laboratory->id,
            'stock' => 30,
            'sales_average' => 5.0,
            'unit_cost' => 1.0,
            'sale_price' => 2.0,
        ]);

        // 4. Producto D: sin ventas pero con stock (Clase C y Stock Muerto)
        $productD = Product::create([
            'name' => 'Antitusígeno Jarabe Raro',
            'laboratory_id' => $this->laboratory->id,
            'stock' => 15,
            'sales_average' => 0.0,
            'unit_cost' => 3.0,
            'sale_price' => 5.0,
        ]);

        // Crear orden completada
        $order = Order::create([
            'order_date' => Carbon::now()->format('Y-m-d'),
            'status' => Order::COMPLETED,
            'total_amount' => 1150.0,
            'total_amount_usd' => 1150.0,
            'money_returns' => 0.0,
            'currency' => 'USD',
        ]);

        OrderDetail::create([
            'order_id' => $order->id,
            'product_id' => $productA->id,
            'product_type' => 'product',
            'quantity' => 100,
            'price' => 8.5,
            'unit_cost' => 5.0,
            'unit_price_usd' => 8.5, // 100 * 8.5 = 850
        ]);

        OrderDetail::create([
            'order_id' => $order->id,
            'product_id' => $productB->id,
            'product_type' => 'product',
            'quantity' => 50,
            'price' => 4.0,
            'unit_cost' => 2.0,
            'unit_price_usd' => 4.0, // 50 * 4.0 = 200
        ]);

        OrderDetail::create([
            'order_id' => $order->id,
            'product_id' => $productC->id,
            'product_type' => 'product',
            'quantity' => 50,
            'price' => 2.0,
            'unit_cost' => 1.0,
            'unit_price_usd' => 2.0, // 50 * 2.0 = 100
        ]);

        $response = $this->getJson('/api/finances/reports/abc-analysis');

        $response->assertOk();
        $this->assertEquals(1150.0, (float) $response->json('grand_total'));

        $items = collect($response->json('data'));

        $itemA = $items->firstWhere('id', $productA->id);
        $this->assertNotNull($itemA);
        $this->assertEquals('A', $itemA['classification']);

        $itemB = $items->firstWhere('id', $productB->id);
        $this->assertNotNull($itemB);
        $this->assertEquals('B', $itemB['classification']);

        $itemC = $items->firstWhere('id', $productC->id);
        $this->assertNotNull($itemC);
        $this->assertEquals('C', $itemC['classification']);

        $itemD = $items->firstWhere('id', $productD->id);
        $this->assertNotNull($itemD);
        $this->assertEquals('C', $itemD['classification']);
        $this->assertTrue((bool) $itemD['is_dead_stock']);
    }

    public function test_can_filter_by_classification_and_dead_stock_coverage(): void
    {
        $productTop = Product::create([
            'name' => 'Paracetamol 500mg',
            'laboratory_id' => $this->laboratory->id,
            'stock' => 200,
            'sales_average' => 50.0,
            'unit_cost' => 1.0,
            'sale_price' => 10.0,
        ]);

        $productMid = Product::create([
            'name' => 'Ibuprofeno 400mg',
            'laboratory_id' => $this->laboratory->id,
            'stock' => 100,
            'sales_average' => 20.0,
            'unit_cost' => 2.0,
            'sale_price' => 5.0,
        ]);

        $productDead = Product::create([
            'name' => 'Colirio Especial Vencible',
            'laboratory_id' => $this->laboratory->id,
            'stock' => 20,
            'sales_average' => 0.0,
            'unit_cost' => 4.0,
            'sale_price' => 7.0,
        ]);

        $order = Order::create([
            'order_date' => Carbon::now()->format('Y-m-d'),
            'status' => Order::COMPLETED,
            'total_amount' => 1000.0,
            'total_amount_usd' => 1000.0,
            'money_returns' => 0.0,
            'currency' => 'USD',
        ]);

        OrderDetail::create([
            'order_id' => $order->id,
            'product_id' => $productTop->id,
            'product_type' => 'product',
            'quantity' => 80,
            'price' => 10.0,
            'unit_cost' => 1.0,
            'unit_price_usd' => 10.0, // 80 * 10 = 800 (80%)
        ]);

        OrderDetail::create([
            'order_id' => $order->id,
            'product_id' => $productMid->id,
            'product_type' => 'product',
            'quantity' => 40,
            'price' => 5.0,
            'unit_cost' => 2.0,
            'unit_price_usd' => 5.0, // 40 * 5 = 200 (20%)
        ]);

        // 1. Filtrar sólo por clasificación 'A'
        $filterAResponse = $this->getJson('/api/finances/reports/abc-analysis?classification=A');
        $filterAResponse->assertOk();

        $itemsA = collect($filterAResponse->json('data'));
        $this->assertNotEmpty($itemsA);
        foreach ($itemsA as $it) {
            $this->assertEquals('A', $it['classification']);
        }

        // 2. Filtrar por stock muerto ('dead_stock')
        $filterDeadResponse = $this->getJson('/api/finances/reports/abc-analysis?coverage_range=dead_stock');
        $filterDeadResponse->assertOk();

        $itemsDead = collect($filterDeadResponse->json('data'));
        $this->assertNotEmpty($itemsDead);
        foreach ($itemsDead as $it) {
            $this->assertTrue((bool) $it['is_dead_stock']);
        }
    }
}
