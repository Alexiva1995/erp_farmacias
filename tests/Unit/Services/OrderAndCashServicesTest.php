<?php

namespace Tests\Unit\Services;

use App\Http\Requests\CashClosure\CloseCashClosureRequest;
use App\Models\CashClosing;
use App\Models\Category;
use App\Models\Client;
use App\Models\DailyCashClosure;
use App\Models\Employee;
use App\Models\ExchangeRate;
use App\Models\ExpenseCategory;
use App\Models\FiscalHistory;
use App\Models\GeneralSetting;
use App\Models\Laboratory;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Origin;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CashClosure\CashClosureActionService;
use App\Services\Order\OrderActionService;
use App\Services\Order\OrderQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OrderAndCashServicesTest extends TestCase
{
    use RefreshDatabase;

    private OrderActionService $orderActionService;
    private OrderQueryService $orderQueryService;
    private CashClosureActionService $cashClosureActionService;

    protected function setUp(): void
    {
        parent::setUp();

        // Resolvemos servicios de órdenes y cierres de caja desde el contenedor
        $this->orderActionService = app(OrderActionService::class);
        $this->orderQueryService = app(OrderQueryService::class);
        $this->cashClosureActionService = app(CashClosureActionService::class);
    }

    /**
     * Inicializa tasas de cambio, configuración general, cliente, cajero y producto con lote.
     */
    private function seedOrderAndCashEnvironment(): array
    {
        ExchangeRate::create([
            'currency_code' => 'BS',
            'rate' => 50.00,
            'source' => 'manual',
        ]);
        ExchangeRate::create([
            'currency_code' => 'COP',
            'rate' => 4000.00,
            'source' => 'manual',
        ]);

        GeneralSetting::create([
            'company_name' => 'Farmacia POS Test',
            'company_rif' => 'J-30123456-0',
            'company_address' => 'Calle Comercial #5',
            'company_phone' => '04125556677',
            'iva_percentage' => 16.00,
            'fiscal_mode' => 'demo',
        ]);

        ExpenseCategory::create([
            'name' => 'Ventas Cierre',
            'description' => 'Categoría general de transacciones',
        ]);

        $adminRole = Role::create([
            'name' => 'admin',
            'display_name' => 'Administrador',
        ]);

        // Creamos primero el usuario admin (id=1) porque OrderQueryService excluye id=1 del ranking de vendedores
        User::create([
            'username' => 'admin_pos',
            'email' => 'admin_pos@example.com',
            'password_hash' => Hash::make('password'),
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        $sellerRole = Role::create([
            'name' => 'cashier',
            'display_name' => 'Cajero',
        ]);

        $seller = User::create([
            'username' => 'cajero_pos',
            'email' => 'cajero_pos@example.com',
            'password_hash' => Hash::make('password'),
            'role_id' => $sellerRole->id,
            'is_active' => true,
        ]);

        Employee::create([
            'user_id' => $seller->id,
            'name' => 'Laura',
            'last_name' => 'Rivas',
            'identification' => 'V-21333444',
            'is_active' => true,
            'total_package_usd' => 240.00,
        ]);

        $this->actingAs($seller);

        $client = Client::create([
            'name' => 'Juan Perez',
            'identification' => 'V-14555666',
            'phone' => '04149998877',
            'address' => 'Centro',
        ]);

        $category = Category::create(['name' => 'Cuidado Personal']);
        $laboratory = Laboratory::create(['name' => 'Lab Salud']);
        $origin = Origin::create(['name' => 'Nacional']);

        $product = Product::create([
            'name' => 'Vitamina C 1000mg',
            'barcode' => '7592223334445',
            'unit_cost' => 2.00,
            'sale_price' => 5.00,
            'units_per_inner_pack' => 1,
            'stock' => 30,
            'category_id' => $category->id,
            'laboratory_id' => $laboratory->id,
            'origin_id' => $origin->id,
            'is_deleted' => false,
        ]);

        $lot = ProductLot::create([
            'product_id' => $product->id,
            'lot_number' => 'LOT-VITC-01',
            'quantity' => 30,
            'unit_cost' => 2.00,
            'expiration_date' => now()->addMonths(12)->toDateString(),
            'entry_date' => now()->toDateString(),
        ]);

        return compact('seller', 'client', 'product', 'lot');
    }

    public function test_order_action_and_query_services_cover_pos_lifecycle_cancellation_and_stats(): void
    {
        $env = $this->seedOrderAndCashEnvironment();
        $seller = $env['seller'];
        $client = $env['client'];
        $product = $env['product'];
        $lot = $env['lot'];

        // Crear orden abierta inicial y consultar órdenes abiertas del vendedor
        $order = $this->orderActionService->createOrder([
            'client_id' => $client->id,
            'seller_id' => $seller->id,
            'currency' => 'USD',
        ]);
        $this->assertSame('Pending', $order->fresh()->status);

        $openOrders = $this->orderActionService->getMyOpenOrder($seller->id);
        $this->assertNotNull($openOrders['pending_order']);

        // Agregar dos ítems y probar eliminación de detalle
        $detail1 = $this->orderActionService->addUpdateOrderItems($order, [
            'product_id' => $product->id,
            'quantity' => 4,
            'price_at_product' => 5.00,
            'currency_at_order' => 'USD',
            'price_usd_unit' => 5.00,
        ]);
        $this->assertInstanceOf(OrderDetail::class, $detail1);

        // Cambiar moneda a BS y devolver a USD
        $bsOrder = $this->orderActionService->updateordenCurrency($order->fresh(), [
            'currency' => 'BS',
            'total_amount' => 1000.00,
            'total_amount_usd' => 20.00,
        ]);
        $this->assertSame('BS', $bsOrder->currency);

        $usdOrder = $this->orderActionService->updateordenCurrency($bsOrder, [
            'currency' => 'USD',
            'total_amount' => 20.00,
            'total_amount_usd' => 20.00,
        ]);
        $this->assertSame('USD', $usdOrder->currency);

        // Completar la orden con pago en efectivo USD
        $completeRequest = new Request([
            'payments' => [
                [
                    'method' => 'cash_usd',
                    'amount' => 20.00,
                    'currency' => 'USD',
                ],
            ],
            'changeAmount' => 0.00,
        ]);

        $completedResponse = $this->orderActionService->complete($usdOrder->fresh(), $completeRequest, $seller->id);
        $this->assertSame('Completed', $usdOrder->fresh()->status);
        $this->assertSame(26, (int) $lot->fresh()->quantity);
        $this->assertIsArray($completedResponse);

        // Crear segunda orden, eliminar detalle, reservar y abandonar orden
        $order2 = $this->orderActionService->createOrder([
            'client_id' => $client->id,
            'seller_id' => $seller->id,
            'currency' => 'USD',
        ]);
        $detail2 = $this->orderActionService->addUpdateOrderItems($order2, [
            'product_id' => $product->id,
            'quantity' => 2,
            'price_at_product' => 5.00,
            'currency_at_order' => 'USD',
            'price_usd_unit' => 5.00,
        ]);
        $this->assertTrue($this->orderActionService->deleteDetail($order2->fresh(), $detail2));

        $this->orderActionService->addUpdateOrderItems($order2->fresh(), [
            'product_id' => $product->id,
            'quantity' => 1,
            'price_at_product' => 5.00,
            'currency_at_order' => 'USD',
            'price_usd_unit' => 5.00,
        ]);
        $reserveRes = $this->orderActionService->reserveOrder($order2->fresh(), $seller->id);
        $this->assertIsArray($reserveRes);

        $abandoned = $this->orderActionService->abandonOrder($order2->fresh());
        $this->assertSame('Abandoned', $abandoned->status);

        // Registrar historial fiscal mínimo para consultas de débito fiscal
        FiscalHistory::create([
            'user_id' => $seller->id,
            'order_id' => $usdOrder->id,
            'invoice_number' => 'F-0001',
            'business_name' => $client->name,
            'identification' => $client->identification,
            'address' => $client->address,
            'exempt_amount' => 0.00,
            'iva_amount' => 160.00,
            'total_amount' => 1160.00,
            'invoice_date' => '2026-06-15',
        ]);

        // Consultas de órdenes y estadísticas
        $startDate = now()->startOfMonth()->toDateString();
        $endDate = now()->endOfMonth()->toDateString();

        $this->assertGreaterThanOrEqual(0, $this->orderQueryService->getForeignOrdersCount());
        $this->assertGreaterThanOrEqual(
            1,
            $this->orderQueryService->getFilteredQuery(new Request(), 'Completed')->count()
        );
        $this->assertGreaterThanOrEqual(
            1,
            $this->orderQueryService->getFilteredQuery(new Request(), 'all')->count()
        );

        $debitoFiscal = $this->orderQueryService->getDebitoFiscal('2026-06-01', '2026-06-30');
        $this->assertIsArray($debitoFiscal);

        $monthlyStats = $this->orderQueryService->getMonthlyStats($startDate, $endDate);
        $this->assertIsArray($monthlyStats);

        $this->assertGreaterThanOrEqual(4, $this->orderQueryService->getUnitsSold($startDate, $endDate));
        $this->assertGreaterThanOrEqual(0.0, $this->orderQueryService->getProfit($startDate, $endDate));
        $this->assertNotEmpty($this->orderQueryService->getPopularProducts(5));
        $this->assertNotEmpty($this->orderQueryService->getEmployeeSalesByAmount((int) now()->year));
        $this->assertNotEmpty($this->orderQueryService->getEmployeeSalesByUnits((int) now()->year));

        // Cancelar orden completada con caja abierta -> debe revertir el stock del lote
        $cancelled = $this->orderActionService->cancelledOrder($usdOrder->fresh());
        $this->assertSame('Cancelled', $cancelled->status);
        $this->assertSame(30, (int) $lot->fresh()->quantity);

        // Si la caja está cerrada, intentar cancelar orden completada lanza excepción
        $completedOrder3 = $this->orderActionService->createOrder([
            'client_id' => $client->id,
            'seller_id' => $seller->id,
            'currency' => 'USD',
        ]);
        $this->orderActionService->addUpdateOrderItems($completedOrder3, [
            'product_id' => $product->id,
            'quantity' => 1,
            'price_at_product' => 5.00,
            'currency_at_order' => 'USD',
            'price_usd_unit' => 5.00,
        ]);
        $this->orderActionService->complete($completedOrder3->fresh(), new Request([
            'payments' => [
                ['method' => 'cash_usd', 'amount' => 5.00, 'currency' => 'USD'],
            ],
            'changeAmount' => 0.00,
        ]), $seller->id);

        CashClosing::where('id', $completedOrder3->fresh()->cash_closing_id)->update([
            'status' => CashClosing::CLOSED,
        ]);

        $this->expectException(\Exception::class);
        $this->orderActionService->cancelledOrder($completedOrder3->fresh());
    }

    public function test_cash_closure_action_service_covers_close_transactions_daily_and_monthly_summary(): void
    {
        $env = $this->seedOrderAndCashEnvironment();
        $seller = $env['seller'];

        $cashClosing = CashClosing::create([
            'seller_id' => $seller->id,
            'status' => CashClosing::OPEN,
            'total_sales' => 250.00,
            'total_usd' => 150.00,
            'total_cop' => 400000.00,
            'total_bs' => 0.00,
            'usd_cash' => 120.00,
            'usd_transfer' => 30.00,
            'cop_cash' => 400000.00,
            'usd_cash_payment_credit' => 10.00,
            'usd_delivered' => 120.00,
            'cop_delivered' => 400000.00,
            'bs_delivered' => 0.00,
            'closing_date' => now(),
        ]);

        // Verificar obtención de caja abierta del vendedor actual
        $openClosure = $this->cashClosureActionService->allCashClosing();
        $this->assertNotNull($openClosure);
        $this->assertSame($cashClosing->id, $openClosure->id);

        // Cerrar caja individual validando Form Request real
        $closureRequest = CloseCashClosureRequest::create('/api/cash-closure/close', 'POST', [
            'id' => $cashClosing->id,
            'total_cop' => 400000.00,
            'sobrante_en_peso' => 0.00,
            'entregar_efectivo_cop' => 400000.00,
            'ticket_html' => '<p>Ticket de cierre</p>',
            'history_html' => '<p>Historial de cierre</p>',
        ]);
        $closureRequest->setContainer(app());
        $closureRequest->setRedirector(app('redirect'));
        $closureRequest->validateResolved();

        $closeResponse = $this->cashClosureActionService->closeCashClosing($closureRequest);
        $this->assertSame(200, $closeResponse->getStatusCode());
        $this->assertSame(CashClosing::CLOSED, $cashClosing->fresh()->status);

        // Ejecutar cierre diario consolidado (crea DailyCashClosure y dispara generateClosingTransactions)
        $this->cashClosureActionService->closeDailyCashClosure();

        $dailyClosure = DailyCashClosure::latest('id')->first();
        $this->assertNotNull($dailyClosure);
        $this->assertSame($dailyClosure->id, $cashClosing->fresh()->daily_closure_id);
        $this->assertGreaterThan(0, Transaction::count());

        // Verificar resumen mensual de ventas por vendedor y agrupación por cierres diarios
        $monthlySummary = $this->cashClosureActionService->getMonthlySalesSummaryData();
        $this->assertIsArray($monthlySummary);
        $this->assertArrayHasKey('current_month_average', $monthlySummary);
        $this->assertArrayHasKey('last_month_average', $monthlySummary);

        $sellersSummary = $this->cashClosureActionService->getCashClosingsForMonthlySummary([$dailyClosure->id]);
        $this->assertIsArray($sellersSummary);
        $this->assertArrayHasKey('summary', $sellersSummary);

        $grouped = $this->cashClosureActionService->getCashClosingsAllSellers([$dailyClosure->id]);
        $this->assertTrue($grouped->has($seller->id));
    }
}
