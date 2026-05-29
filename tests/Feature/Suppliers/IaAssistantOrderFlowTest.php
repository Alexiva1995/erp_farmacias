<?php

namespace Tests\Feature\Suppliers;

use App\Models\Product;
use App\Models\ProductSupplier;
use App\Models\Supplier;
use App\Models\User;
use App\Models\AutoOrder;
use App\Models\AutoOrderDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IaAssistantOrderFlowTest extends TestCase
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

        // Crear producto con cantidad manual y sin ignorar
        $this->product = Product::create([
            'name' => 'Amoxicilina 500mg',
            'unit_cost' => 2.00,
            'sale_price' => 3.50,
            'manual_solicitar' => 15,
            'ignore_until' => null,
        ]);

        // Crear enlace de producto con proveedor
        $this->productSupplier = ProductSupplier::create([
            'product_id' => $this->product->id,
            'supplier_id' => $this->supplier->id,
            'name' => 'Amoxicilina 500mg Genérico',
            'unit_cost_usd' => 1.80,
            'unit_cost' => 1.80,
            'connection_date' => now(),
        ]);
    }

    /**
     * Prueba que al añadir un producto a la orden desde el asistente de IA:
     * - El producto se añade correctamente.
     * - NO se marca como ignorado (ignore_until sigue null).
     * - Se limpia la cantidad manual sugerida (manual_solicitar es null).
     */
    public function test_add_to_order_does_not_ignore_product(): void
    {
        $payload = [
            'product_id' => $this->product->id,
            'quantity' => 10,
            'supplier_id' => $this->supplier->id,
            'product_supplier_id' => $this->productSupplier->id,
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/suppliers-ia-order-assistant/add-to-order', $payload);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'auto_order_id',
            ]);

        // 1. Verificar en base de datos que se creó la orden de compra y el detalle
        $autoOrder = AutoOrder::where('supplier_id', $this->supplier->id)->first();
        $this->assertNotNull($autoOrder);

        $detail = AutoOrderDetail::where('order_id', $autoOrder->id)
            ->where('product_id', $this->product->id)
            ->first();
        $this->assertNotNull($detail);
        $this->assertEquals(10, $detail->quantity);

        // 2. Verificar que el producto NO fue marcado como ignorado
        $this->product->refresh();
        $this->assertNull($this->product->ignore_until);

        // 3. Verificar que la cantidad manual fue restablecida a null
        $this->assertNull($this->product->manual_solicitar);
    }
}
