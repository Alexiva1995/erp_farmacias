<?php

namespace Tests\Feature\Suppliers;

use App\Contracts\PurchaseOrder;
use App\Contracts\Suppliers\CristmedicalsApiServiceInterface;
use App\Enums\AutoOrderStatus;
use App\Models\AutoOrder;
use App\Models\AutoOrderDetail;
use App\Models\GeneralSetting;
use App\Models\ProductSupplier;
use App\Models\Supplier;
use App\Models\SupplierConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CristmedicalsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_order_api_successfully_sends_payload_to_cristmedicals(): void
    {
        Http::fake([
            'https://apienterprise.cristmedicals.com/api/v1/pedidos' => Http::response([
                'status' => 'success',
                'message' => 'Pedido recibido correctamente',
                'id_pedido' => 1001,
            ], 200),
            'https://ve.dolarapi.com/*' => Http::response([
                'promedio' => 36.50,
            ], 200),
        ]);

        GeneralSetting::create([
            'app_name' => 'Farmacia Barrio Sucre C.A.',
            'app_rif' => 'J-12345678-9',
        ]);

        $supplier = Supplier::create([
            'name' => 'CRISTMEDICALS, C.A.',
            'rif' => 'J-31234567-8',
            'dispatch_days' => ['monday', 'wednesday'],
            'order_days' => ['monday', 'tuesday'],
        ]);

        SupplierConnection::create([
            'supplier_id' => $supplier->id,
            'type' => 'api',
            'host' => 'https://apienterprise.cristmedicals.com',
            'username' => 'FAR00818',
            'password' => 'test_token',
            'pasv' => true,
            'has_header' => false,
        ]);

        $order = AutoOrder::create([
            'supplier_id' => $supplier->id,
            'order_date' => '2026-09-15',
            'total_items' => 1,
            'total_quantity' => 2,
            'total_amount' => 100.0,
            'status' => AutoOrderStatus::PENDING,
        ]);

        $prod = ProductSupplier::create([
            'supplier_id' => $supplier->id,
            'cod_supplier' => 'AMP00100C',
            'name' => '9 VIT INFUSION MULTIVITAMINICA AMP I.V',
            'barcode_match' => '646824105066',
            'unit_cost' => 50.0,
            'unit_cost_usd' => 50.0,
            'quantity' => 421,
            'connection_date' => now(),
        ]);

        AutoOrderDetail::create([
            'order_id' => $order->id,
            'product_suppliers_id' => $prod->id,
            'quantity' => 2,
            'unit_cost' => 50.0,
            'subtotal' => 100.0,
        ]);

        $service = app(CristmedicalsApiServiceInterface::class);
        $result = $service->sendOrderApi($order);

        $this->assertTrue($result['success']);

        Http::assertSent(function ($request) use ($order) {
            return $request->url() === 'https://apienterprise.cristmedicals.com/api/v1/pedidos'
                && $request['fact_num'] === $order->id
                && $request['co_cli'] === 'FAR00818'
                && count($request['renglones']) === 1
                && $request['renglones'][0]['co_art'] === 'AMP00100C'
                && $request['renglones'][0]['total_art'] === 2
                && $request['renglones'][0]['prec_vta'] === 50.0
                && $request['renglones'][0]['reng_neto'] === 100.0;
        });
    }

    public function test_confirm_sent_triggers_cristmedicals_api_service(): void
    {
        $supplier = Supplier::create([
            'name' => 'CRISTMEDICALS',
            'rif' => 'J-31234567-8',
            'dispatch_days' => ['monday'],
            'order_days' => ['monday'],
        ]);

        $order = AutoOrder::create([
            'supplier_id' => $supplier->id,
            'order_date' => '2026-09-15',
            'total_items' => 1,
            'total_quantity' => 2,
            'total_amount' => 50,
            'status' => AutoOrderStatus::PENDING,
        ]);

        $mockApi = $this->createMock(CristmedicalsApiServiceInterface::class);
        $mockApi->expects($this->once())
            ->method('sendOrderApi')
            ->with($this->callback(fn($o) => $o->id === $order->id))
            ->willReturn([
                'success' => true,
                'message' => 'OK',
            ]);

        $this->app->instance(CristmedicalsApiServiceInterface::class, $mockApi);

        $purchaseOrderService = app(PurchaseOrder::class);
        $result = $purchaseOrderService->confirmSent($order);

        $this->assertTrue($result);
        $this->assertEquals(AutoOrderStatus::SENT, $order->fresh()->status);
    }
}