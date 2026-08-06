<?php

namespace Tests\Feature\POS;

use App\Models\User;
use App\Models\Client;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Credit;
use App\Models\CreditPayment;
use App\Models\CashClosing;
use App\Models\ExchangeRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClientCreditAndPaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $cajero;
    private Client $client;
    private Product $product;
    private CashClosing $openCash;

    protected function setUp(): void
    {
        parent::setUp();

        // Limpiar cache de tasas de cambio
        Cache::forget('resources.all_exchange_rates');

        // Sembrar los roles necesarios en la BD
        $this->seed(\Database\Seeders\RolesSeeder::class);

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

        // Crear usuario Administrador (role_id = 1) para eliminaciones y gestión total
        $this->admin = User::create([
            'username' => 'admin_creditos',
            'role_id' => 1,
            'email' => 'admin_cred@farmacia.com',
            'password_hash' => bcrypt('password'),
            'is_active' => true,
        ]);

        // Crear usuario Cajero (role_id = 2) para cobranzas y ventas
        $this->cajero = User::create([
            'username' => 'cajero_creditos',
            'role_id' => 2,
            'email' => 'cajero_cred@farmacia.com',
            'password_hash' => bcrypt('password'),
            'is_active' => true,
        ]);

        // Crear un cliente VIP de prueba
        $this->client = Client::create([
            'identification' => '25123456',
            'identification_type' => Client::IDENTIFICATION_TYPE_VENEZOLANO,
            'name' => 'María',
            'last_name' => 'Gómez',
            'email' => 'maria@farmacia.com',
            'phone' => '04147654321',
            'address' => 'Avenida Principal de la Farmacia',
            'balance' => 0.0,
            'is_spe' => false,
            'status' => 1,
            'client_type' => Client::CLIENT_TYPE_VIP,
        ]);

        // Crear un producto con código de barras de prueba
        $this->product = Product::create([
            'name' => 'Amoxicilina 500mg',
            'unit_cost' => 1.50,
            'sale_price' => 2.50,
            'iva' => 0,
            'barcode' => '7501234567890',
        ]);

        // Crear una caja abierta para el cajero
        $this->openCash = CashClosing::create([
            'seller_id' => $this->cajero->id,
            'status' => CashClosing::OPEN,
            'closing_date' => now(),
        ]);
    }

    /**
     * =================================================================================================
     * FASE 2: GESTIÓN E HISTORIAL DE CRÉDITOS
     * =================================================================================================
     */

    /**
     * Valida la obtención de la lista de créditos paginados.
     */
    public function test_can_list_client_credits(): void
    {
        // Crear una orden y un crédito pendiente
        $order = Order::create([
            'client_id' => $this->client->id,
            'seller_id' => $this->cajero->id,
            'currency' => 'USD',
            'status' => 'Pending',
            'cash_closing_id' => $this->openCash->id,
            'total_amount' => 5.00,
            'total_amount_usd' => 5.00,
            'total_cost' => 0.00,
            'money_returns' => 0.00,
            'usd_conversion' => 0.00,
            'taxable_base' => 0.00,
            'spe_surcharge_rate' => 0.00,
            'spe_surcharge_amount' => 0.00,
        ]);

        Credit::create([
            'client_id' => $this->client->id,
            'order_id' => $order->id,
            'credit_amount' => 5.00,
            'pending_amount' => 5.00,
            'credit_date' => now(),
            'status' => 'Active',
        ]);

        $response = $this->actingAs($this->cajero, 'sanctum')
            ->getJson('/api/tpv/credits?client=' . $this->client->name);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'total',
            ]);
    }

    /**
     * Valida la obtención de los detalles específicos de un crédito.
     */
    public function test_can_show_credit_details(): void
    {
        $order = Order::create([
            'client_id' => $this->client->id,
            'seller_id' => $this->cajero->id,
            'currency' => 'USD',
            'status' => 'Pending',
            'cash_closing_id' => $this->openCash->id,
            'total_amount' => 10.00,
            'total_amount_usd' => 10.00,
            'total_cost' => 0.00,
            'money_returns' => 0.00,
            'usd_conversion' => 0.00,
            'taxable_base' => 0.00,
            'spe_surcharge_rate' => 0.00,
            'spe_surcharge_amount' => 0.00,
        ]);

        $credit = Credit::create([
            'client_id' => $this->client->id,
            'order_id' => $order->id,
            'credit_amount' => 10.00,
            'pending_amount' => 10.00,
            'credit_date' => now(),
            'status' => 'Active',
        ]);

        $response = $this->actingAs($this->cajero, 'sanctum')
            ->postJson('/api/tpv/credits/details', [
                'credit_ids' => [$credit->id],
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'id' => $credit->id,
            ]);
    }

    /**
     * Valida la obtención del historial de amortizaciones del cliente.
     */
    public function test_can_get_payment_history_of_client(): void
    {
        CreditPayment::create([
            'client_id' => $this->client->id,
            'seller_id' => $this->cajero->id,
            'cash_closing_id' => $this->openCash->id,
            'money_returns' => 0.00,
            'payment_date' => now(),
            'method_Payment' => [
                [
                    'method' => 'cash_usd',
                    'amount' => 15.00,
                    'currency' => 'USD',
                ]
            ],
        ]);

        $response = $this->actingAs($this->cajero, 'sanctum')
            ->postJson('/api/tpv/credits/payments', [
                'client_id' => $this->client->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'amount' => 15.00,
                'method' => 'cash_usd',
            ]);
    }

    /**
     * Valida el listado global de cobros (payments) condicional de SQLite.
     */
    public function test_can_list_all_credit_payments(): void
    {
        CreditPayment::create([
            'client_id' => $this->client->id,
            'seller_id' => $this->cajero->id,
            'cash_closing_id' => $this->openCash->id,
            'money_returns' => 0.00,
            'payment_date' => now(),
            'method_Payment' => [
                [
                    'method' => 'cash_usd',
                    'amount' => 20.00,
                    'currency' => 'USD',
                ]
            ],
        ]);

        $response = $this->actingAs($this->cajero, 'sanctum')
            ->getJson('/api/tpv/credits/payments');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'total',
            ]);
    }

    /**
     * =================================================================================================
     * FASE 3: AMORTIZACIÓN DE DEUDAS E INTEGRIDAD DE CAJA
     * =================================================================================================
     */

    /**
     * Valida la amortización (abono de deudas) parcial y total de un crédito de cliente.
     */
    public function test_can_complete_credit_payments_successfully(): void
    {
        $order = Order::create([
            'client_id' => $this->client->id,
            'seller_id' => $this->cajero->id,
            'currency' => 'USD',
            'status' => 'Pending',
            'cash_closing_id' => $this->openCash->id,
            'total_amount' => 50.00,
            'total_amount_usd' => 50.00,
            'total_cost' => 0.00,
            'money_returns' => 0.00,
            'usd_conversion' => 0.00,
            'taxable_base' => 0.00,
            'spe_surcharge_rate' => 0.00,
            'spe_surcharge_amount' => 0.00,
        ]);

        // Crear deuda de 50.00
        $credit = Credit::create([
            'client_id' => $this->client->id,
            'order_id' => $order->id,
            'credit_amount' => 50.00,
            'pending_amount' => 50.00,
            'credit_date' => now(),
            'status' => 'Active',
        ]);

        // Abonar 30.00 en efectivo USD (Amortización Parcial)
        $response = $this->actingAs($this->cajero, 'sanctum')
            ->postJson('/api/tpv/credits/complete', [
                'clientId' => $this->client->id,
                'payments' => [
                    [
                        'method' => 'cash_usd',
                        'amount' => 30.00,
                        'currency' => 'USD',
                    ]
                ],
                'changeAmount' => 0.00,
            ]);

        $response->assertStatus(200);

        // La deuda pendiente debe reducirse a 20.00 y seguir activa
        $credit->refresh();
        $this->assertEquals(20.00, $credit->pending_amount);
        $this->assertEquals('Active', $credit->status);

        // La caja abierta del cajero debe haber acumulado los 30.00 cobrados
        $this->openCash->refresh();
        $this->assertEquals(30.00, (float) $this->openCash->usd_cash_payment_credit);

        // Abonar los 20.00 restantes (Amortización Total)
        $response2 = $this->actingAs($this->cajero, 'sanctum')
            ->postJson('/api/tpv/credits/complete', [
                'clientId' => $this->client->id,
                'payments' => [
                    [
                        'method' => 'cash_usd',
                        'amount' => 20.00,
                        'currency' => 'USD',
                    ]
                ],
                'changeAmount' => 0.00,
            ]);

        $response2->assertStatus(200);

        // La deuda pendiente debe ser 0.00 y el estado debe ser 'Paid'
        $credit->refresh();
        $this->assertEquals(0.00, $credit->pending_amount);
        $this->assertEquals('Paid', $credit->status);

        // La caja debe tener los 50.00 totales acumulados
        $this->openCash->refresh();
        $this->assertEquals(50.00, (float) $this->openCash->usd_cash_payment_credit);
    }

    /**
     * Valida que solo administradores puedan eliminar créditos y bloquee a cajeros/empleados.
     */
    public function test_restricted_delete_credit_for_non_admins(): void
    {
        $order = Order::create([
            'client_id' => $this->client->id,
            'seller_id' => $this->cajero->id,
            'currency' => 'USD',
            'status' => 'Pending',
            'cash_closing_id' => $this->openCash->id,
            'total_amount' => 10.00,
            'total_amount_usd' => 10.00,
            'total_cost' => 0.00,
            'money_returns' => 0.00,
            'usd_conversion' => 0.00,
            'taxable_base' => 0.00,
            'spe_surcharge_rate' => 0.00,
            'spe_surcharge_amount' => 0.00,
        ]);

        $credit = Credit::create([
            'client_id' => $this->client->id,
            'order_id' => $order->id,
            'credit_amount' => 10.00,
            'pending_amount' => 10.00,
            'credit_date' => now(),
            'status' => 'Active',
        ]);

        // 1. Intentar eliminar siendo Cajero (role_id = 2) -> debe responder 403 Forbidden
        $responseForbidden = $this->actingAs($this->cajero, 'sanctum')
            ->deleteJson('/api/tpv/credits', [
                'credit_ids' => [$credit->id],
            ]);

        $responseForbidden->assertStatus(403);
        $this->assertDatabaseHas('credits', ['id' => $credit->id]);

        // 2. Eliminar siendo Administrador (role_id = 1) -> debe responder 200 OK
        $responseOk = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson('/api/tpv/credits', [
                'credit_ids' => [$credit->id],
            ]);

        $responseOk->assertStatus(200);
        $this->assertDatabaseMissing('credits', ['id' => $credit->id]);
    }

    /**
     * Valida la actualización de estado de créditos por el supervisor.
     */
    public function test_supervisor_can_update_credit_status(): void
    {
        $order = Order::create([
            'client_id' => $this->client->id,
            'seller_id' => $this->cajero->id,
            'currency' => 'USD',
            'status' => 'Pending',
            'cash_closing_id' => $this->openCash->id,
            'total_amount' => 10.00,
            'total_amount_usd' => 10.00,
            'total_cost' => 0.00,
            'money_returns' => 0.00,
            'usd_conversion' => 0.00,
            'taxable_base' => 0.00,
            'spe_surcharge_rate' => 0.00,
            'spe_surcharge_amount' => 0.00,
        ]);

        $credit = Credit::create([
            'client_id' => $this->client->id,
            'order_id' => $order->id,
            'credit_amount' => 10.00,
            'pending_amount' => 10.00,
            'credit_date' => now(),
            'status' => 'Active',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/tpv/credits/status', [
                'ids' => [$credit->id],
                'status' => 'Paid',
            ]);

        $response->assertStatus(200);
        $credit->refresh();
        $this->assertEquals('Paid', $credit->status);
    }

    /**
     * =================================================================================================
     * FASE 4: INTEGRACIÓN DEL FLUJO DE VENTAS A CRÉDITO EN EL POS
     * =================================================================================================
     */

    /**
     * Valida que al finalizar una venta marcando la opción de crédito en el POS,
     * se descuente el inventario de medicamentos y se cree el registro de crédito activo para el cliente.
     */
    public function test_pos_sale_with_credit_option_creates_credit_automatically(): void
    {
        // 1. Crear el lote del producto con stock de 10 unidades
        $lot = ProductLot::create([
            'product_id' => $this->product->id,
            'lot_number' => 'LOT-CRED-01',
            'expiration_date' => now()->addYear(),
            'quantity' => 10,
            'unit_cost' => 1.50,
        ]);

        // 2. Crear una orden y agregar 2 unidades del producto
        // Total de la orden: 2 * 2.50 = 5.00
        $order = Order::create([
            'client_id' => $this->client->id,
            'seller_id' => $this->cajero->id,
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

        // 3. Completar la orden marcando crédito
        $response = $this->actingAs($this->cajero, 'sanctum')
            ->postJson("/api/tpv/orders/{$order->id}/complete", [
                'credit' => true,
                'client_id' => $this->client->id,
                'payments' => [
                    [
                        'method' => 'credit',
                        'amount' => 5.00,
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

        // B. Stock físico en product_lots debe haber bajado de 10 a 8
        $lot->refresh();
        $this->assertEquals(8, $lot->quantity);

        // C. Se debe haber creado el registro de crédito activo asociado por los 5.00
        $this->assertDatabaseHas('credits', [
            'client_id' => $this->client->id,
            'order_id' => $order->id,
            'credit_amount' => 5.00,
            'pending_amount' => 5.00,
            'status' => 'Active',
        ]);
    }
}
