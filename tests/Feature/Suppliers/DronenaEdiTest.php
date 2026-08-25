<?php

namespace Tests\Feature\Suppliers;

use App\Contracts\PurchaseOrder;
use App\Contracts\Suppliers\DronenaEdiServiceInterface;
use App\Models\AutoOrder;
use App\Models\AutoOrderDetail;
use App\Models\ProductSupplier;
use App\Models\Supplier;
use App\Models\SupplierConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DronenaEdiTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_order_content_matches_dronena_specification(): void
    {
        $supplier = Supplier::create([
            'name' => 'DROGUERIA NENA, C.A.',
            'rif' => 'J085189777',
            'dispatch_days' => ['monday', 'wednesday'],
            'order_days' => ['monday', 'tuesday'],
        ]);

        $order = AutoOrder::create([
            'supplier_id' => $supplier->id,
            'order_date' => '2026-08-25',
            'total_items' => 2,
            'total_quantity' => 5,
            'total_amount' => 100,
            'status' => \App\Enums\AutoOrderStatus::PENDING,
        ]);

        $prod1 = ProductSupplier::create([
            'supplier_id' => $supplier->id,
            'cod_supplier' => 'GG074',
            'name' => 'PANTOP AMP. IV 40MG.',
            'barcode_match' => '7591234567890',
            'unit_cost' => 10.0,
            'unit_cost_usd' => 0.25,
            'quantity' => 100,
            'connection_date' => now(),
        ]);

        $prod2 = ProductSupplier::create([
            'supplier_id' => $supplier->id,
            'cod_supplier' => 'EL555',
            'name' => 'TANTUM NEBULIZADOR 45 CC.',
            'barcode_match' => '7591234567891',
            'unit_cost' => 20.0,
            'unit_cost_usd' => 0.50,
            'quantity' => 50,
            'connection_date' => now(),
        ]);

        AutoOrderDetail::create([
            'order_id' => $order->id,
            'product_suppliers_id' => $prod1->id,
            'quantity' => 2,
            'unit_cost' => 10.0,
            'subtotal' => 20.0,
        ]);

        AutoOrderDetail::create([
            'order_id' => $order->id,
            'product_suppliers_id' => $prod2->id,
            'quantity' => 3,
            'unit_cost' => 20.0,
            'subtotal' => 60.0,
        ]);

        $service = app(DronenaEdiServiceInterface::class);
        $content = $service->generateOrderContent($order);

        $this->assertStringContainsString("D000 ", $content);
        $this->assertStringContainsString("D001 GG074", $content);
        $this->assertStringContainsString("D002 2", $content);
        $this->assertStringContainsString("D003 PANTOP AMP. IV 40MG.", $content);
        $this->assertStringContainsString("D001 EL555", $content);
        $this->assertStringContainsString("D002 3", $content);
        $this->assertStringContainsString("D003 TANTUM NEBULIZADOR 45 CC.", $content);
        $this->assertStringContainsString("\r\n", $content);
    }

    public function test_confirm_sent_triggers_dronena_edi_service(): void
    {
        $supplier = Supplier::create([
            'name' => 'DROGUERIA NENA, C.A.',
            'rif' => 'J085189777',
            'dispatch_days' => ['monday', 'wednesday'],
            'order_days' => ['monday', 'tuesday'],
        ]);

        $order = AutoOrder::create([
            'supplier_id' => $supplier->id,
            'order_date' => '2026-08-25',
            'total_items' => 1,
            'total_quantity' => 2,
            'total_amount' => 20,
            'status' => \App\Enums\AutoOrderStatus::PENDING,
        ]);

        $mockEdi = $this->createMock(DronenaEdiServiceInterface::class);
        $mockEdi->expects($this->once())
            ->method('sendOrderFtp')
            ->with($this->callback(fn($o) => $o->id === $order->id))
            ->willReturn([
                'success' => true,
                'filename' => 'FACTU01',
                'remote_path' => 'Clientes/d719/FACTU01',
                'message' => 'OK'
            ]);

        $this->app->instance(DronenaEdiServiceInterface::class, $mockEdi);

        $purchaseOrderService = app(PurchaseOrder::class);
        $result = $purchaseOrderService->confirmSent($order);

        $this->assertTrue($result);
        $this->assertEquals(\App\Enums\AutoOrderStatus::SENT, $order->fresh()->status);
    }
}
