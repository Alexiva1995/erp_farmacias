<?php

namespace Tests\Feature\Suppliers;

use App\Contracts\PurchaseOrder;
use App\Contracts\Suppliers\VitalclinicFtpServiceInterface;
use App\Models\AutoOrder;
use App\Models\AutoOrderDetail;
use App\Models\ProductSupplier;
use App\Models\Supplier;
use App\Models\SupplierConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VitalclinicFtpTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_order_content_matches_vitalclinic_specification(): void
    {
        $supplier = Supplier::create([
            'name' => 'DROGUERÍA VITALCLINIC',
            'rif' => 'J412002260',
            'dispatch_days' => ['monday', 'wednesday'],
            'order_days' => ['monday', 'tuesday'],
        ]);

        $order = AutoOrder::create([
            'supplier_id' => $supplier->id,
            'order_date' => '2026-08-26',
            'total_items' => 2,
            'total_quantity' => 15,
            'total_amount' => 150.50,
            'status' => \App\Enums\AutoOrderStatus::PENDING,
        ]);

        $prod1 = ProductSupplier::create([
            'supplier_id' => $supplier->id,
            'cod_supplier' => '100025',
            'name' => 'ACETAMINOFEN 500MG TAB',
            'barcode_match' => '7591234567890',
            'unit_cost' => 5.25,
            'unit_cost_usd' => 5.25,
            'quantity' => 100,
            'connection_date' => now(),
        ]);

        $prod2 = ProductSupplier::create([
            'supplier_id' => $supplier->id,
            'cod_supplier' => '100088',
            'name' => 'IBUPROFENO 400MG TAB',
            'barcode_match' => '7591234567891',
            'unit_cost' => 10.00,
            'unit_cost_usd' => 10.00,
            'quantity' => 50,
            'connection_date' => now(),
        ]);

        AutoOrderDetail::create([
            'order_id' => $order->id,
            'product_suppliers_id' => $prod1->id,
            'quantity' => 5,
            'unit_cost' => 5.25,
            'subtotal' => 26.25,
        ]);

        AutoOrderDetail::create([
            'order_id' => $order->id,
            'product_suppliers_id' => $prod2->id,
            'quantity' => 10,
            'unit_cost' => 10.00,
            'subtotal' => 100.00,
        ]);

        $service = app(VitalclinicFtpServiceInterface::class);
        $content = $service->generateOrderContent($order);

        $this->assertStringContainsString("100025;ACETAMINOFEN 500MG TAB;5;5.25", $content);
        $this->assertStringContainsString("100088;IBUPROFENO 400MG TAB;10;10.00", $content);
        $this->assertStringContainsString("\r\n", $content);
    }

    public function test_confirm_sent_triggers_vitalclinic_ftp_service(): void
    {
        $supplier = Supplier::create([
            'name' => 'DROGUERÍA VITALCLINIC',
            'rif' => 'J412002260',
            'dispatch_days' => ['monday', 'wednesday'],
            'order_days' => ['monday', 'tuesday'],
        ]);

        $order = AutoOrder::create([
            'supplier_id' => $supplier->id,
            'order_date' => '2026-08-26',
            'total_items' => 1,
            'total_quantity' => 5,
            'total_amount' => 50.00,
            'status' => \App\Enums\AutoOrderStatus::PENDING,
        ]);

        $mockFtpService = $this->createMock(VitalclinicFtpServiceInterface::class);
        $mockFtpService->expects($this->once())
            ->method('sendOrderFtp')
            ->with($this->callback(function (AutoOrder $passedOrder) use ($order) {
                return $passedOrder->id === $order->id;
            }))
            ->willReturn([
                'success' => true,
                'filename' => '3613P000001.txt',
                'remote_path' => 'Pedidos/3613P000001.txt',
                'message' => 'OK',
            ]);

        $this->app->instance(VitalclinicFtpServiceInterface::class, $mockFtpService);

        $purchaseOrderService = app(PurchaseOrder::class);
        $result = $purchaseOrderService->confirmSent($order);

        $this->assertTrue($result);
        $order->refresh();
        $this->assertEquals(\App\Enums\AutoOrderStatus::SENT, $order->status);
    }
}
