<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use App\Models\Client;
use App\Models\GeneralSetting;
use App\Models\InventoryCycle;
use App\Models\InventoryMovement;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\ProductCount;
use App\Models\ProductLot;
use App\Models\ReturnEntry;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Expirations\ExpirationActionService;
use App\Services\InventoryCycle\InventoryCycleActionService;
use App\Services\Invoices\InvoiceActionService;
use App\Services\Returns\ReturnsActionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComprehensiveTraceabilityAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $cashier;
    private Supplier $supplier;
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesSeeder::class);

        $this->admin = User::create([
            'username' => 'super_admin',
            'role_id' => 1,
            'email' => 'admin@erp.com',
            'password_hash' => bcrypt('password'),
            'is_active' => true,
        ]);

        $this->cashier = User::create([
            'username' => 'cajero_test',
            'role_id' => 2,
            'email' => 'cajero@erp.com',
            'password_hash' => bcrypt('password'),
            'is_active' => true,
        ]);

        $this->supplier = Supplier::create([
            'name' => 'Drogueria Test C.A.',
            'social_reason' => 'Drogueria Test C.A.',
            'rif' => 'J-12345678-0',
            'dispatch_days' => 1,
            'order_days' => 1,
        ]);

        $this->client = Client::create([
            'name' => 'Juan Perez',
            'identification' => '12345678',
            'identification_type' => Client::IDENTIFICATION_TYPE_VENEZOLANO,
        ]);

        GeneralSetting::updateOrCreate(['id' => 1], [
            'enable_lots' => true,
            'cyclic_inventory_scope' => 'all',
            'cyclic_inventory_daily_quota' => 50,
            'cyclic_inventory_mode' => 'complex',
        ]);
    }

    /**
     * 1. Valida que caducar un lote genere exactamente UN solo movimiento de tipo 'expired' y no duplicados.
     */
    public function test_expiring_lot_creates_exactly_one_movement_without_duplicates(): void
    {
        $this->actingAs($this->admin, 'sanctum');

        $product = Product::create([
            'name' => 'Paracetamol 500mg',
            'barcode' => '7501111111111',
            'unit_cost' => 1.00,
            'sale_price' => 2.50,
            'stock' => 20,
        ]);

        $lot = ProductLot::create([
            'product_id' => $product->id,
            'lot_number' => 'LOT-EXP-01',
            'quantity' => 20,
            'expiration_date' => now()->subDay(),
            'unit_cost' => 1.00,
        ]);

        $expirationService = app(ExpirationActionService::class);
        $expirationService->expireLot($lot);

        $lot->refresh();
        $product->refresh();

        $this->assertEquals(0, $lot->quantity);
        $this->assertEquals(0, $product->stock);

        $newMovements = InventoryMovement::where('product_id', $product->id)
            ->where('movement_type', 'expired')
            ->get();

        $this->assertCount(1, $newMovements);
        $this->assertEquals(-20, (float) $newMovements->first()->quantity);
        $this->assertEquals(20, (float) $newMovements->first()->stock_before);
        $this->assertEquals(0, (float) $newMovements->first()->stock_after);
    }

    /**
     * 2. Valida que al aprobar una factura de compra se creen exactamente los movimientos de tipo 'purchase'.
     */
    public function test_invoice_purchase_approval_creates_exact_purchase_movements(): void
    {
        $this->actingAs($this->admin, 'sanctum');

        $product = Product::create([
            'name' => 'Vitamina C 1g',
            'barcode' => '7502222222222',
            'unit_cost' => 0.50,
            'sale_price' => 1.50,
            'stock' => 0,
        ]);

        $invoice = Invoice::create([
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'FAC-9999',
            'control_number' => 'CTRL-9999',
            'exp_date' => now()->addDays(30),
            'received_date' => now(),
            'created_invoice_date' => now(),
            'total_amount' => 50.00,
            'currency' => 'USD',
            'status' => 'loaded',
            'registered_by' => $this->admin->id,
            'uploaded_by' => $this->admin->id,
        ]);

        $invoice->details()->create([
            'product_id' => $product->id,
            'quantity' => 100,
            'unit_cost' => 0.50,
            'total_cost' => 50.00,
            'lot_number' => 'LOT-VITC',
            'expiration_date' => now()->addYear()->format('Y-m-d'),
        ]);

        $invoiceService = app(InvoiceActionService::class);
        $invoiceService->approveInvoice($invoice, ['exchange_rate' => 1]);

        $purchaseMovements = InventoryMovement::where('product_id', $product->id)
            ->where('movement_type', 'purchase')
            ->get();

        $this->assertCount(1, $purchaseMovements);
        $this->assertEquals(100, (float) $purchaseMovements->first()->quantity);
        $this->assertEquals($invoice->id, $purchaseMovements->first()->invoice_id);
        $this->assertEquals(100, (float) $product->fresh()->stock);
    }

    /**
     * 3. Valida que al concretar una venta se descuente el inventario y se cree el movimiento 'sale'.
     */
    public function test_order_sale_completion_creates_exact_sale_movements(): void
    {
        $this->actingAs($this->cashier, 'sanctum');

        $product = Product::create([
            'name' => 'Omeprazol 20mg',
            'barcode' => '7503333333333',
            'unit_cost' => 1.20,
            'sale_price' => 4.00,
            'stock' => 50,
        ]);

        $lot = ProductLot::create([
            'product_id' => $product->id,
            'lot_number' => 'LOT-OMEP-01',
            'quantity' => 50,
            'expiration_date' => now()->addYear(),
            'unit_cost' => 1.20,
        ]);

        $order = Order::create([
            'seller_id' => $this->cashier->id,
            'client_id' => $this->client->id,
            'total_amount' => 12.00,
            'currency' => 'USD',
            'money_returns' => 0,
            'status' => Order::PENDING,
            'order_date' => now(),
        ]);

        OrderDetail::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_lot_id' => $lot->id,
            'product_type' => 'product',
            'unit_cost' => 1.20,
            'quantity' => 3,
            'price' => 4.00,
            'total' => 12.00,
            'unit_price_usd' => 4.00,
        ]);

        $lot->decrement('quantity', 3);
        $order->update(['status' => Order::COMPLETED]);

        $saleMovements = InventoryMovement::where('product_id', $product->id)
            ->where('movement_type', 'sale')
            ->get();

        $this->assertCount(1, $saleMovements);
        $this->assertEquals(-3, (float) $saleMovements->first()->quantity);
        $this->assertEquals($order->id, $saleMovements->first()->order_id);
        $this->assertEquals(47, (float) $product->fresh()->stock);
    }

    /**
     * 4. Valida que una devolución aprobada reingrese stock y cree movimiento 'return'.
     */
    public function test_return_approval_with_distribution_creates_exact_return_movement(): void
    {
        $this->actingAs($this->admin, 'sanctum');

        $product = Product::create([
            'name' => 'Loratadina 10mg',
            'barcode' => '7504444444444',
            'unit_cost' => 0.80,
            'sale_price' => 2.00,
            'stock' => 10,
        ]);

        $lot = ProductLot::create([
            'product_id' => $product->id,
            'lot_number' => 'LOT-LORA-01',
            'quantity' => 10,
            'expiration_date' => now()->addYear(),
            'unit_cost' => 0.80,
        ]);

        $order = Order::create([
            'seller_id' => $this->cashier->id,
            'client_id' => $this->client->id,
            'total_amount' => 4.00,
            'currency' => 'USD',
            'money_returns' => 0,
            'status' => Order::COMPLETED,
            'order_date' => now(),
        ]);

        $returnEntry = ReturnEntry::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'amount_refunded' => 4.00,
            'generated_by_id' => $this->cashier->id,
            'return_date' => now(),
            'status' => null,
        ]);

        $returnsService = app(ReturnsActionService::class);
        $returnsService->approveWithDistribution($returnEntry, [
            ['id' => $lot->id, 'quantity' => 12],
        ], []);

        $returnMovements = InventoryMovement::where('product_id', $product->id)
            ->where('movement_type', 'return')
            ->get();

        $this->assertCount(1, $returnMovements);
        $this->assertEquals(2, (float) $returnMovements->first()->quantity);
        $this->assertEquals($order->id, $returnMovements->first()->order_id);
        $this->assertEquals(12, (float) $product->fresh()->stock);
    }

    /**
     * 5. Valida que el conteo cíclico con discrepancia genere exactamente el movimiento con su product_count_id.
     */
    public function test_cyclic_count_with_discrepancy_and_approval_creates_single_movement_with_count_id(): void
    {
        $this->actingAs($this->admin, 'sanctum');

        $cycle = InventoryCycle::create([
            'start_date' => now(),
            'status' => 'active',
        ]);

        $product = Product::create([
            'name' => 'Cetirizina 10mg',
            'barcode' => '7505555555555',
            'unit_cost' => 1.50,
            'sale_price' => 3.50,
            'stock' => 10,
        ]);

        $lot = ProductLot::create([
            'product_id' => $product->id,
            'lot_number' => 'LOT-CET-01',
            'quantity' => 10,
            'expiration_date' => now()->addYear(),
            'unit_cost' => 1.50,
        ]);

        $cyclicService = app(InventoryCycleActionService::class);
        $resCount = $cyclicService->createProductCount($product->id, [
            'counted_quantity' => 15,
            'system_quantity' => 10,
            'discrepancy' => 5,
            'barcode' => '7505555555555',
        ]);

        $this->assertTrue($resCount['success']);
        $count = $resCount['data'];
        $this->assertEquals('pending', $count->status);

        $resApprove = $cyclicService->processAction($count, 'approve', [
            'updated_lots' => [
                ['id' => $lot->id, 'quantity' => 15]
            ]
        ]);

        $this->assertTrue($resApprove['success']);

        $adjustMovements = InventoryMovement::where('product_id', $product->id)
            ->where('movement_type', 'adjustment')
            ->get();

        $this->assertCount(1, $adjustMovements);
        $this->assertEquals(5, (float) $adjustMovements->first()->quantity);
        $this->assertEquals($count->id, $adjustMovements->first()->product_count_id);
        $this->assertEquals(15, (float) $product->fresh()->stock);
    }

    /**
     * 6. Valida que para el Admin no se limite la consulta a 50 productos si está en modo cuotas.
     */
    public function test_admin_gets_all_products_in_cyclic_inventory_without_quota_restriction(): void
    {
        GeneralSetting::updateOrCreate(['id' => 1], [
            'cyclic_inventory_scope' => 'quota',
            'cyclic_inventory_daily_quota' => 2,
        ]);

        $cycle = InventoryCycle::create([
            'start_date' => now(),
            'status' => 'active',
        ]);

        for ($i = 1; $i <= 5; $i++) {
            Product::create([
                'name' => "Producto Test {$i}",
                'barcode' => "750999999999{$i}",
                'unit_cost' => 1.00,
                'sale_price' => 2.00,
                'stock' => 10,
            ]);
        }

        $responseAdmin = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/inventory/products?itemsPerPage=10');

        $responseAdmin->assertStatus(200);
        $this->assertEquals(5, $responseAdmin->json('total'));

        $responseQuota = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/inventory/user-quota-status');

        $responseQuota->assertStatus(200)
            ->assertJsonPath('is_active', false);
    }
}
