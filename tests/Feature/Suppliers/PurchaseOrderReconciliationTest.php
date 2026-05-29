<?php

namespace Tests\Feature\Suppliers;

use App\Models\AutoOrder;
use App\Models\AutoOrderDetail;
use App\Models\Product;
use App\Models\ProductSupplier;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\AutoOrderDetailStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Supplier $supplier;
    private Product $product;
    private ProductSupplier $productSupplier;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear usuario para autenticación
        $this->user = User::create([
            'username' => 'testadmin',
            'email' => 'admin@test.com',
            'password_hash' => bcrypt('password'),
            'is_active' => true,
        ]);

        // Crear proveedor
        $this->supplier = Supplier::create([
            'name' => 'Proveedor de Prueba',
            'rif' => 'J-12345678-9',
            'address' => 'Dirección de prueba',
            'phone' => '1234567',
            'dispatch_days' => [],
            'order_days' => [],
        ]);

        // Crear producto
        $this->product = Product::create([
            'name' => 'Paracetamol 500mg',
            'unit_cost' => 1.50,
            'sale_price' => 2.50,
        ]);

        // Crear enlace de producto con proveedor
        $this->productSupplier = ProductSupplier::create([
            'product_id' => $this->product->id,
            'supplier_id' => $this->supplier->id,
            'name' => 'Paracetamol 500mg Genérico',
            'unit_cost_usd' => 1.40,
            'unit_cost' => 1.40,
            'connection_date' => now(),
        ]);
    }

    /**
     * Prueba que al abrir los detalles de una orden "Enviada" (status = 1),
     * los productos se marquen automáticamente como recibidos si están en facturas cargadas.
     */
    public function test_automatic_reconciliation_on_view_details(): void
    {
        // 1. Crear la orden de compra en estado Enviada (status = 1)
        $order = AutoOrder::create([
            'supplier_id' => $this->supplier->id,
            'order_date' => now(),
            'total_items' => 1,
            'total_quantity' => 10,
            'total_amount' => 14.00,
            'status' => 1, // Enviada
        ]);

        $orderDetail = AutoOrderDetail::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_suppliers_id' => $this->productSupplier->id,
            'quantity' => 10,
            'unit_cost' => 1.40,
            'subtotal' => 14.00,
            'received' => null, // Pendiente
            'status' => 0, // Pendiente
        ]);

        // 2. Crear una factura cargada (status = 'loaded') del mismo proveedor conteniendo el producto
        $invoice = Invoice::create([
            'supplier_id' => $this->supplier->id,
            'auto_order_id' => null, // Ojo: no asociamos por id de orden directamente, se prueba la búsqueda global por proveedor
            'invoice_number' => 'FAC-99999',
            'control_number' => 'CTRL-99999',
            'exp_date' => now()->addYear(),
            'received_date' => now(),
            'created_invoice_date' => now(),
            'total_amount' => 14.00,
            'currency' => 'USD',
            'status' => 'loaded',
            'uploaded_by' => $this->user->id,
            'registered_by' => $this->user->id,
        ]);

        InvoiceDetail::create([
            'invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_cost' => 1.40,
            'total_cost' => 14.00,
            'lot_number' => 'LOTE-123',
            'expiration_date' => now()->addYear()->toDateString(),
            'location' => 'A1',
        ]);

        // 3. Hacer la petición para ver los detalles de la orden
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/suppliers/purchase-orders/{$order->id}");

        $response->assertStatus(200);

        // 4. Verificar que se haya reconciliado en la base de datos automáticamente
        $orderDetail->refresh();
        $this->assertEquals(1, $orderDetail->received);
        $this->assertEquals(AutoOrderDetailStatus::ARRIVED->value, $orderDetail->status);
    }
}
