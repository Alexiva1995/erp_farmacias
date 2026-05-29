<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\ExpirationOffer;
use App\Models\ExpiredLog;
use App\Models\PriceAdjustmentLog;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\CashClosing;
use App\Models\ExchangeRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExpirationAndOfferTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $cajero;
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

        // Crear usuario Administrador (role_id = 1) para desincorporación y gestión de ofertas
        $this->admin = User::create([
            'username' => 'admin_vencimiento',
            'role_id' => 1,
            'email' => 'admin_ven@farmacia.com',
            'password_hash' => bcrypt('password'),
            'is_active' => true,
        ]);

        // Crear usuario Cajero (role_id = 2) para ventas
        $this->cajero = User::create([
            'username' => 'cajero_vencimiento',
            'role_id' => 2,
            'email' => 'cajero_ven@farmacia.com',
            'password_hash' => bcrypt('password'),
            'is_active' => true,
        ]);

        // Crear un producto con código de barras e IVA de prueba
        $this->product = Product::create([
            'name' => 'Ibuprofeno 400mg',
            'unit_cost' => 1.00,
            'sale_price' => 2.00,
            'iva' => 0,
            'barcode' => '7509876543210',
            'stock' => 10,
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
     * FASE 1: GESTIÓN DE OFERTAS DE CADUCIDAD (Expiration Offers)
     * =================================================================================================
     */

    /**
     * Valida la obtención de la lista de ofertas paginadas.
     */
    public function test_can_list_expiration_offers(): void
    {
        ExpirationOffer::create([
            'months_to_expiration' => 3,
            'discount_percentage' => 15.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/tpv/promotions/expiration-offer');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'total',
            ])
            ->assertJsonFragment([
                'months_to_expiration' => 3,
                'discount_percentage' => 15.00,
            ]);
    }

    /**
     * Valida que un administrador pueda crear exitosamente una oferta de caducidad.
     */
    public function test_admin_can_create_expiration_offer_successfully(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/tpv/promotions/expiration-offer', [
                'months_to_expiration' => 6,
                'discount_percentage' => 20.50,
                'is_active' => true,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Oferta creada exitosamente',
            ]);

        $this->assertDatabaseHas('expiration_offers', [
            'months_to_expiration' => 6,
            'discount_percentage' => 20.50,
            'is_active' => true,
        ]);
    }

    /**
     * Valida que no se pueda crear una oferta duplicada para el mismo número de meses si ya hay una activa.
     */
    public function test_cannot_create_duplicate_active_expiration_offer(): void
    {
        // Crear una oferta activa existente para 3 meses
        ExpirationOffer::create([
            'months_to_expiration' => 3,
            'discount_percentage' => 10.00,
            'is_active' => true,
        ]);

        // Intentar crear otra oferta activa para los mismos 3 meses
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/tpv/promotions/expiration-offer', [
                'months_to_expiration' => 3,
                'discount_percentage' => 20.00,
                'is_active' => true,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Ya existe una oferta activa para este periodo de caducidad (3 meses).',
            ]);
    }

    /**
     * Valida que se pueda actualizar una oferta de caducidad.
     */
    public function test_admin_can_update_expiration_offer(): void
    {
        $offer = ExpirationOffer::create([
            'months_to_expiration' => 4,
            'discount_percentage' => 12.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/tpv/promotions/expiration-offer/{$offer->id}", [
                'discount_percentage' => 18.00,
                'is_active' => false,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Oferta actualizada exitosamente',
            ]);

        $offer->refresh();
        $this->assertEquals(18.00, $offer->discount_percentage);
        $this->assertFalse((bool) $offer->is_active);
    }

    /**
     * Valida que no se pueda actualizar una oferta si genera un conflicto con otra activa.
     */
    public function test_cannot_update_expiration_offer_to_conflict_with_another(): void
    {
        // Oferta 1: 2 meses (activa)
        ExpirationOffer::create([
            'months_to_expiration' => 2,
            'discount_percentage' => 10.00,
            'is_active' => true,
        ]);

        // Oferta 2: 5 meses (activa)
        $offer2 = ExpirationOffer::create([
            'months_to_expiration' => 5,
            'discount_percentage' => 15.00,
            'is_active' => true,
        ]);

        // Intentar actualizar la Oferta 2 a 2 meses (generaría conflicto con Oferta 1)
        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/tpv/promotions/expiration-offer/{$offer2->id}", [
                'months_to_expiration' => 2,
                'is_active' => true,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Ya existe otra oferta activa para 2 meses.',
            ]);
    }

    /**
     * Valida la eliminación de una oferta de caducidad.
     */
    public function test_admin_can_delete_expiration_offer(): void
    {
        $offer = ExpirationOffer::create([
            'months_to_expiration' => 8,
            'discount_percentage' => 5.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/tpv/promotions/expiration-offer/{$offer->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Oferta eliminada exitosamente',
            ]);

        $this->assertDatabaseMissing('expiration_offers', [
            'id' => $offer->id,
        ]);
    }

    /**
     * Valida la obtención de lotes disponibles con stock real para ofertas.
     */
    public function test_get_available_product_lots_for_offers(): void
    {
        // Crear un lote con stock de 10 unidades que vence en un año
        ProductLot::create([
            'product_id' => $this->product->id,
            'lot_number' => 'LOT-EXP-1',
            'expiration_date' => now()->addYear(),
            'quantity' => 10,
            'unit_cost' => 1.00,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/tpv/promotions/expiration-offer/available-product-lots');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    }

    /**
     * =================================================================================================
     * FASE 2: CONTROL DE VENCIMIENTOS, EXPIRACIÓN Y REAJUSTE DE COSTOS
     * =================================================================================================
     */

    /**
     * Valida la desincorporación física de un lote próximo a expirar (marcarlo como caducado).
     */
    public function test_admin_can_expire_single_product_lot(): void
    {
        $lot = ProductLot::create([
            'product_id' => $this->product->id,
            'lot_number' => 'LOT-BAD-1',
            'expiration_date' => now()->subDay(), // Expirado ayer
            'quantity' => 5,
            'unit_cost' => 1.20,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/lots/{$lot->id}/expire");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Lote marcado como caducado con éxito.',
            ]);

        // El stock del lote debe pasar a 0
        $lot->refresh();
        $this->assertEquals(0, $lot->quantity);

        // Se debe registrar en la tabla de logs de caducidad
        $this->assertDatabaseHas('expired_logs', [
            'lot_id' => $lot->id,
            'product_id' => $this->product->id,
            'expired_quantity' => 5,
            'cost_per_unit' => 1.20,
            'total_lost_value' => 6.00, // 5 * 1.20 = 6.00
        ]);
    }

    /**
     * Valida la desincorporación en lote de múltiples elementos.
     */
    public function test_admin_can_expire_multiple_product_lots(): void
    {
        $lot1 = ProductLot::create([
            'product_id' => $this->product->id,
            'lot_number' => 'LOT-BAD-A',
            'expiration_date' => now()->subDay(),
            'quantity' => 2,
            'unit_cost' => 1.00,
        ]);

        $lot2 = ProductLot::create([
            'product_id' => $this->product->id,
            'lot_number' => 'LOT-BAD-B',
            'expiration_date' => now()->subDay(),
            'quantity' => 3,
            'unit_cost' => 1.00,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/lots/expire-multiple', [
                'lot_ids' => [$lot1->id, $lot2->id],
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => '2 lotes caducados con éxito.',
            ]);

        $lot1->refresh();
        $lot2->refresh();
        $this->assertEquals(0, $lot1->quantity);
        $this->assertEquals(0, $lot2->quantity);
    }

    /**
     * Valida la previsualización del reajuste de precios de un mes específico.
     */
    public function test_preview_price_adjustment(): void
    {
        // Forzar creación de registros vencidos en el mes actual en BD
        $month = now()->format('Y-m');

        $lot = ProductLot::create([
            'product_id' => $this->product->id,
            'lot_number' => 'LOT-X',
            'expiration_date' => now()->subMonth(),
            'quantity' => 10,
            'unit_cost' => 2.00,
        ]);

        // Crear el log de expirados manualmente para el mes
        ExpiredLog::create([
            'lot_id' => $lot->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'lot_number' => $lot->lot_number,
            'expired_quantity' => 10,
            'cost_per_unit' => 2.00,
            'total_lost_value' => 20.00,
            'created_at' => now(), // Creado hoy (mes actual)
        ]);

        // Debe existir otro producto con stock activo (> 0) para recibir la redistribución
        $productActivo = Product::create([
            'name' => 'Paracetamol 500mg',
            'unit_cost' => 1.50,
            'sale_price' => 3.00,
            'iva' => 0,
            'stock' => 5, // 5 unidades activas
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/expirations/adjust-prices/preview', [
                'month' => $month,
                'excludedProductIds' => [],
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'total_lost_value',
                'total_active_stock',
                'cost_adjustment_per_unit',
                'affected_products_count',
            ]);
    }

    /**
     * Valida la ejecución y aplicación financiera del reajuste del costo unitario.
     */
    public function test_adjust_expired_products_prices(): void
    {
        $month = now()->format('Y-m');

        $lot = ProductLot::create([
            'product_id' => $this->product->id,
            'lot_number' => 'LOT-X',
            'expiration_date' => now()->subMonth(),
            'quantity' => 10,
            'unit_cost' => 2.00,
        ]);

        // Log de expirados del mes actual
        $expiredLog = ExpiredLog::create([
            'lot_id' => $lot->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'lot_number' => $lot->lot_number,
            'expired_quantity' => 10,
            'cost_per_unit' => 2.00,
            'total_lost_value' => 20.00,
            'created_at' => now(),
        ]);

        // Producto activo con 10 unidades de stock para redistribuir
        $productActivo = Product::create([
            'name' => 'Paracetamol 500mg',
            'unit_cost' => 1.00,
            'sale_price' => 3.00,
            'iva' => 0,
            'stock' => 10,
        ]);

        // El valor perdido es 20.00, stock activo es 10 + 10 (del Ibuprofeno que también tiene stock = 10, total 20 stock activo)
        // Ojo, Ibuprofeno tiene stock = 10. Total stock activo = 20. Ajuste por unidad = 20 / 20 = 1.00
        // Paracetamol nuevo costo = 1.00 (anterior) + 1.00 (ajuste) = 2.00

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/expirations/adjust-expired-prices', [
                'month' => $month,
                'excludedProductIds' => [],
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'affected_products_count' => 2,
                'total_cost_redistributed' => 20.00,
            ]);

        // Validar que se actualizó el costo del producto activo
        $productActivo->refresh();
        $this->assertEquals(2.00, $productActivo->unit_cost);

        // Validar que se creó el log del reajuste
        $this->assertDatabaseHas('price_adjustment_logs', [
            'month' => $month,
            'expired_log_id' => $expiredLog->id,
            'cost_redistributed' => 20.00,
        ]);
    }

    /**
     * =================================================================================================
     * FASE 3: INTEGRACIÓN DE VENCIMIENTOS CON EL POS (Ventas Reales)
     * =================================================================================================
     */

    /**
     * Valida que al vender un producto con un lote próximo a expirar que tiene una oferta de caducidad,
     * el sistema aplique automáticamente el descuento exacto.
     */
    public function test_pos_sale_applies_expiration_offer_discount_automatically(): void
    {
        // 1. Crear una oferta activa de caducidad para lotes que expiran en 3 meses o menos
        // Aplicar un 25% de descuento
        $offer = ExpirationOffer::create([
            'months_to_expiration' => 3,
            'discount_percentage' => 25.00,
            'is_active' => true,
        ]);

        // 2. Crear un lote de stock del producto que expira dentro de 2 meses (vence en 3 meses o menos)
        // Por ende, califica para la oferta
        $lot = ProductLot::create([
            'product_id' => $this->product->id,
            'lot_number' => 'LOT-EXP-2M',
            'expiration_date' => now()->addMonths(2)->startOfMonth(),
            'quantity' => 10,
            'unit_cost' => 1.00,
        ]);

        // 3. Crear una orden vacía en el POS
        $order = Order::create([
            'client_id' => null,
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

        // 4. Agregar el producto a la orden a través del endpoint de POS
        // Se agregará 1 unidad. El precio de venta del Ibuprofeno es 2.00
        // Descuento aplicable: 25%. Precio descontado: 2.00 * 0.75 = 1.50
        $response = $this->actingAs($this->cajero, 'sanctum')
            ->postJson("/api/tpv/orders/{$order->id}/items", [
                'product_id' => $this->product->id,
                'quantity' => 1,
                'price_at_product' => 2.00,
                'currency_at_order' => 'USD',
                'price_usd_unit' => 2.00,
            ]);

        $response->assertStatus(201);

        // Verificar que el subtotal y totales de la orden reflejen el descuento
        $order->refresh();
        $this->assertEquals(1.50, $order->total_amount); // 2.00 - 25% = 1.50

        // Verificar en los detalles de la orden que se guarde la trazabilidad del tipo de descuento
        $detail = OrderDetail::where('order_id', $order->id)->first();
        $this->assertNotNull($detail);
        $this->assertEquals(25.00, $detail->discount_percentage);
        $this->assertEquals('expiration', $detail->discount_type);
        $this->assertEquals($offer->id, $detail->discount_source_id);

        // 5. Completar la orden en el POS
        $responseComplete = $this->actingAs($this->cajero, 'sanctum')
            ->postJson("/api/tpv/orders/{$order->id}/complete", [
                'payments' => [
                    [
                        'method' => 'cash_usd',
                        'amount' => 1.50,
                        'currency' => 'USD',
                    ]
                ],
                'changeAmount' => 0.00,
            ]);

        $responseComplete->assertStatus(200);

        // El stock del lote debe reducirse a 9
        $lot->refresh();
        $this->assertEquals(9, $lot->quantity);
    }
}
