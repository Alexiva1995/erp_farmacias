<?php

namespace Tests\Feature\POS;

use App\Models\User;
use App\Models\Client;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\CashClosing;
use App\Models\ExchangeRate;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PosOrderFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;
    private Client $client;
    private Supplier $supplier;
    private Product $product;
    private CashClosing $openCash;

    protected function setUp(): void
    {
        parent::setUp();

        // Limpiar cache de tasas de cambio
        Cache::forget('resources.all_exchange_rates');

        // Sembrar tasas de cambio necesarias en BD
        ExchangeRate::create(['currency_code' => 'USD', 'rate' => 1.0, 'source' => 'test']);
        ExchangeRate::create(['currency_code' => 'BS', 'rate' => 36.0, 'source' => 'test']);
        ExchangeRate::create(['currency_code' => 'COP', 'rate' => 4000.0, 'source' => 'test']);
        ExchangeRate::create(['currency_code' => 'EUR', 'rate' => 39.0, 'source' => 'test']);

        // Sembrar configuración general de prueba
        DB::table('general_settings')->insert([
            'fiscal_mode' => 'demo',
            'special_taxpayer_status' => 'desactivada',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Crear un usuario vendedor/cajero
        $this->seller = User::create([
            'username' => 'cajero1',
            'email' => 'cajero1@farmacia.com',
            'password_hash' => bcrypt('password'),
            'is_active' => true,
        ]);

        // Crear un cliente de prueba con status = 1 (entero)
        $this->client = Client::create([
            'identification' => '12345678',
            'identification_type' => Client::IDENTIFICATION_TYPE_VENEZOLANO,
            'name' => 'John',
            'last_name' => 'Doe',
            'email' => 'johndoe@test.com',
            'phone' => '04121234567',
            'address' => 'Dirección de prueba',
            'balance' => 0.0,
            'is_spe' => false,
            'status' => 1,
            'client_type' => Client::CLIENT_TYPE_NUEVO,
        ]);

        // Crear un proveedor
        $this->supplier = Supplier::create([
            'name' => 'Droguería de Prueba',
            'rif' => 'J-12345678-0',
            'address' => 'Dirección Proveedor',
            'phone' => '12345678',
            'dispatch_days' => [],
            'order_days' => [],
        ]);

        // Crear un producto con IVA = 0 por defecto
        $this->product = Product::create([
            'name' => 'Acetaminofén 500mg',
            'unit_cost' => 1.50,
            'sale_price' => 2.50,
            'iva' => 0,
        ]);

        // Crear una caja abierta para el vendedor
        $this->openCash = CashClosing::create([
            'seller_id' => $this->seller->id,
            'status' => CashClosing::OPEN,
            'closing_date' => now(),
        ]);
    }

    /**
     * Valida que se pueda crear una orden vacía vinculada al vendedor.
     */
    public function test_create_empty_order_successfully(): void
    {
        $response = $this->actingAs($this->seller, 'sanctum')
            ->postJson('/api/tpv/orders', [
                'client_id' => $this->client->id,
                'seller_id' => $this->seller->id,
            ]);

        $response->assertStatus(201);
        $this->assertEquals('success', $response->json('status'));
        
        $orderId = $response->json('data.order.id');
        $this->assertNotNull($orderId);
        
        // Consultar el registro de base de datos para refrescar los valores por defecto
        $order = Order::findOrFail($orderId);
        $this->assertEquals($this->client->id, $order->client_id);
        $this->assertEquals($this->seller->id, $order->seller_id);
        $this->assertEquals('Pending', $order->status);
    }

    /**
     * Valida que se pueda agregar un producto a la orden y los totales se recalculen.
     */
    public function test_add_product_to_order_recalculates_totals(): void
    {
        // 1. Crear stock en inventario a través de un lote del producto
        ProductLot::create([
            'product_id' => $this->product->id,
            'supplier_id' => $this->supplier->id,
            'lot_number' => 'LOT-002',
            'expiration_date' => now()->addYear(),
            'quantity' => 10,
            'unit_cost' => 1.50,
        ]);

        // 2. Crear una orden primero
        $order = Order::create([
            'client_id' => $this->client->id,
            'seller_id' => $this->seller->id,
            'currency' => 'USD',
            'status' => 'Pending',
            'cash_closing_id' => $this->openCash->id,
            'total_amount' => 0.00,
            'total_amount_usd' => 0.00,
            'total_cost' => 0.00,
            'money_returns' => 0.00,
            'usd_conversion' => 0.00,
            'taxable_base' => 0.00,
            'spe_surcharge_rate' => 0.00,
            'spe_surcharge_amount' => 0.00,
        ]);

        // 3. Intentar agregar un ítem al carrito de la orden
        $response = $this->actingAs($this->seller, 'sanctum')
            ->postJson("/api/tpv/orders/{$order->id}/items", [
                'product_id' => $this->product->id,
                'quantity' => 2,
                'price_at_product' => 2.50,
                'currency_at_order' => 'USD',
                'price_usd_unit' => 2.50,
            ]);

        $response->assertStatus(201);

        // 4. Validar que la orden tenga el detalle y totales actualizados
        $order->refresh();
        $this->assertCount(1, $order->details);
        $this->assertEquals(5.00, $order->total_amount);
        $this->assertEquals(5.00, $order->total_amount_usd);
    }

    /**
     * Valida la finalización exitosa de la venta con descuento de stock y actualización de caja.
     */
    public function test_complete_order_reduces_stock_and_updates_cash_register(): void
    {
        // 1. Crear el lote del producto con stock de 10 unidades
        $lot = ProductLot::create([
            'product_id' => $this->product->id,
            'supplier_id' => $this->supplier->id,
            'lot_number' => 'LOT-001',
            'expiration_date' => now()->addYear(),
            'quantity' => 10,
            'unit_cost' => 1.50,
        ]);

        // 2. Crear una orden y agregar 3 unidades del producto
        $order = Order::create([
            'client_id' => $this->client->id,
            'seller_id' => $this->seller->id,
            'currency' => 'USD',
            'status' => 'Pending',
            'cash_closing_id' => $this->openCash->id,
            'total_amount' => 0.00,
            'total_amount_usd' => 0.00,
            'total_cost' => 0.00,
            'money_returns' => 0.00,
            'usd_conversion' => 0.00,
            'taxable_base' => 0.00,
            'spe_surcharge_rate' => 0.00,
            'spe_surcharge_amount' => 0.00,
        ]);

        OrderDetail::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 3,
            'price' => 2.50,
            'unit_cost' => 1.50,
            'unit_price_usd' => 2.50,
            'product_type' => 'normal',
        ]);

        // Recalcular totales de la orden
        $order->updateTotals();

        // 3. Completar la orden mediante pago en divisas (USD Cash)
        $response = $this->actingAs($this->seller, 'sanctum')
            ->postJson("/api/tpv/orders/{$order->id}/complete", [
                'payments' => [
                    [
                        'method' => 'cash_usd',
                        'amount' => 7.50,
                        'currency' => 'USD',
                    ]
                ],
                'changeAmount' => 0.00,
            ]);

        $response->assertStatus(200);

        // 4. Aserciones de integridad:
        // A. Estado de la orden debe ser Completed
        $order->refresh();
        $this->assertEquals('Completed', $order->status);

        // B. Stock físico en product_lots debe haber bajado de 10 a 7
        $lot->refresh();
        $this->assertEquals(7, $lot->quantity);

        // C. El cierre de caja abierto del vendedor debe haber acumulado los 7.50 en usd_cash
        $this->openCash->refresh();
        $this->assertEquals(7.50, (float) $this->openCash->usd_cash);
    }

    /**
     * Valida que una discrepancia en el monto del pago arroje un error 422 en lugar de un error 500.
     */
    public function test_complete_order_throws_validation_error_on_payment_discrepancy(): void
    {
        // 1. Crear el lote del producto con stock
        ProductLot::create([
            'product_id' => $this->product->id,
            'supplier_id' => $this->supplier->id,
            'lot_number' => 'LOT-002',
            'expiration_date' => now()->addYear(),
            'quantity' => 10,
            'unit_cost' => 1.50,
        ]);

        // 2. Crear una orden y agregar 2 unidades del producto (Total = 5.00 USD)
        $order = Order::create([
            'client_id' => $this->client->id,
            'seller_id' => $this->seller->id,
            'currency' => 'USD',
            'status' => 'Pending',
            'cash_closing_id' => $this->openCash->id,
            'total_amount' => 0.00,
            'total_amount_usd' => 0.00,
            'total_cost' => 0.00,
            'money_returns' => 0.00,
            'usd_conversion' => 0.00,
            'taxable_base' => 0.00,
            'spe_surcharge_rate' => 0.00,
            'spe_surcharge_amount' => 0.00,
        ]);

        OrderDetail::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price' => 2.50,
            'unit_cost' => 1.50,
            'unit_price_usd' => 2.50,
            'product_type' => 'normal',
        ]);

        $order->updateTotals();

        // 3. Completar con pago menor a lo requerido (enviamos 3.00 USD en vez de 5.00 USD)
        $response = $this->actingAs($this->seller, 'sanctum')
            ->postJson("/api/tpv/orders/{$order->id}/complete", [
                'payments' => [
                    [
                        'method' => 'cash_usd',
                        'amount' => 3.00, // Discrepancia aquí
                        'currency' => 'USD',
                    ]
                ],
                'changeAmount' => 0.00,
            ]);

        // 4. Validar que retorne 422 y la firma de error PAYMENT_DISCREPANCY
        $response->assertStatus(422);
        $response->assertJsonPath('error_code', 'PAYMENT_DISCREPANCY');
        $response->assertJsonStructure([
            'success',
            'error_code',
            'message',
            'data' => [
                'net_paid',
                'order_total',
                'currency'
            ]
        ]);
    }
}
