<?php

declare(strict_types=1);

namespace Tests\Feature\Suppliers;

use App\Models\AutoOrder;
use App\Models\AutoOrderDetail;
use App\Models\Laboratory;
use App\Models\Product;
use App\Models\ProductSupplier;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderByLaboratoryTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create([
            'username' => 'testuser',
            'email' => 'user@test.com',
            'password_hash' => bcrypt('password'),
            'is_active' => true,
        ]);
    }

    public function test_can_get_aggregated_purchase_orders_by_laboratory(): void
    {
        $supplier = Supplier::create([
            'name' => 'Droguería Test',
            'rif' => 'J-12345678-0',
            'address' => 'Test Address',
            'phone' => '1234567890',
            'dispatch_days' => [],
            'order_days' => [],
            'is_active' => true,
        ]);
        $lab = Laboratory::create([
            'name' => 'Laboratorio Bayer',
        ]);
        $product = Product::create([
            'name' => 'Aspirina 500mg',
            'laboratory_id' => $lab->id,
            'barcode' => '7591234567890',
            'unit_cost' => 5.0,
            'sale_price' => 7.0,
            'sales_average' => 10,
        ]);
        $ps = ProductSupplier::create([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'unit_cost_usd' => 5.0,
            'unit_cost' => 5.0,
            'laboratory' => 'Bayer',
            'connection_date' => now(),
        ]);

        $order = AutoOrder::create([
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'status' => 0,
            'total_items' => 1,
            'total_quantity' => 10,
            'total_amount' => 50.0,
        ]);

        AutoOrderDetail::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_suppliers_id' => $ps->id,
            'quantity' => 10,
            'unit_cost' => 5.0,
            'subtotal' => 50.0,
            'status' => 0,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/suppliers/purchase-orders-laboratory');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        '*' => [
                            'laboratory_id',
                            'laboratory_name',
                            'total_skus',
                            'total_units',
                            'total_amount_usd',
                        ]
                    ],
                    'total',
                ]
            ]);

        $this->assertEquals('Laboratorio Bayer', $response->json('data.data.0.laboratory_name'));
        $this->assertEquals(1, $response->json('data.data.0.total_skus'));
        $this->assertEquals(10, $response->json('data.data.0.total_units'));
        $this->assertEquals(50.0, $response->json('data.data.0.total_amount_usd'));
    }

    public function test_can_get_laboratory_details_with_supplier_info(): void
    {
        $supplier = Supplier::create([
            'name' => 'Droguería Nena',
            'rif' => 'J-12345678-1',
            'address' => 'Test Address Nena',
            'phone' => '1234567891',
            'dispatch_days' => [],
            'order_days' => [],
            'is_active' => true,
        ]);
        $lab = Laboratory::create([
            'name' => 'Laboratorio Pfizer',
        ]);
        $product = Product::create([
            'name' => 'Paracetamol 500mg',
            'laboratory_id' => $lab->id,
            'barcode' => '7591234567891',
            'unit_cost' => 2.0,
            'sale_price' => 3.0,
            'sales_average' => 20,
        ]);
        $ps = ProductSupplier::create([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'unit_cost_usd' => 2.0,
            'unit_cost' => 2.0,
            'laboratory' => 'Pfizer',
            'connection_date' => now(),
        ]);

        $order = AutoOrder::create([
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'status' => 0,
            'total_items' => 1,
            'total_quantity' => 20,
            'total_amount' => 40.0,
        ]);

        AutoOrderDetail::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_suppliers_id' => $ps->id,
            'quantity' => 20,
            'unit_cost' => 2.0,
            'subtotal' => 40.0,
            'status' => 0,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/suppliers/purchase-orders-laboratory/{$lab->id}/details");

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.data.0.product_name', 'PARACETAMOL 500MG')
            ->assertJsonPath('data.data.0.supplier_name', 'Droguería Nena')
            ->assertJsonPath('data.data.0.quantity', 20)
            ->assertJsonPath('data.data.0.unit_cost', 2)
            ->assertJsonPath('data.data.0.subtotal', 40);
    }
}
