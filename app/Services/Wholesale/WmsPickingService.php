<?php

declare(strict_types=1);

namespace App\Services\Wholesale;

use App\Enums\B2BOrderStatusEnum;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Services\Tenancy\InterTenantB2BBridgeService;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WmsPickingService
{
    public function __construct(
        protected InterTenantB2BBridgeService $interTenantBridge
    ) {}

    /**
     * Listado de pedidos en cola para almacén mayorista (Picking, Checking, Packing, Despacho).
     */
    public function getWmsOrders(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = Order::query()
            ->with(['client:id,name,identification,delivery_route_id', 'deliveryRoute:id,code,name', 'picker:id,name', 'checker:id,name', 'details.product:id,name,barcode', 'details.lot'])
            ->whereNotNull('credit_term_days');

        if (!empty($filters['workflow_status'])) {
            $query->where('b2b_workflow_status', $filters['workflow_status']);
        } else {
            // Por defecto, mostrar órdenes activas en el flujo de almacén
            $query->whereIn('b2b_workflow_status', [
                B2BOrderStatusEnum::APPROVED_CREDIT->value,
                B2BOrderStatusEnum::PICKING->value,
                B2BOrderStatusEnum::CHECKING->value,
                B2BOrderStatusEnum::PACKED->value,
                B2BOrderStatusEnum::DISPATCHED->value,
            ]);
        }

        if (!empty($filters['delivery_route_id'])) {
            $query->where('delivery_route_id', $filters['delivery_route_id']);
        }

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhereHas('client', function ($clientQ) use ($search) {
                      $clientQ->where('name', 'like', "%{$search}%")
                              ->orWhere('identification', 'like', "%{$search}%");
                  });
            });
        }

        return $query->orderBy('order_date', 'asc')->paginate($perPage);
    }

    /**
     * Inicia el proceso de Picking asignando al operario de almacén.
     */
    public function startPicking(int $orderId, int $pickerUserId): Order
    {
        $order = Order::findOrFail($orderId);

        if ($order->b2b_workflow_status !== B2BOrderStatusEnum::APPROVED_CREDIT->value) {
            throw ValidationException::withMessages([
                'order' => 'La orden debe tener crédito aprobado para iniciar picking.',
            ]);
        }

        $order->update([
            'b2b_workflow_status' => B2BOrderStatusEnum::PICKING->value,
            'assigned_picker_id' => $pickerUserId,
        ]);

        return $order;
    }

    /**
     * Confirma las cantidades recolectadas en Picking y avanza a Doble Chequeo (Checking).
     */
    public function completePicking(int $orderId, array $pickedItems): Order
    {
        $order = Order::findOrFail($orderId);

        return DB::transaction(function () use ($order, $pickedItems) {
            foreach ($pickedItems as $item) {
                $detailId = (int) $item['order_detail_id'];
                $pickedQty = (float) $item['picked_quantity'];

                OrderDetail::where('id', $detailId)
                    ->where('order_id', $order->id)
                    ->update(['picked_quantity' => $pickedQty]);
            }

            $order->update([
                'b2b_workflow_status' => B2BOrderStatusEnum::CHECKING->value,
            ]);

            return $order;
        });
    }

    /**
     * Doble chequeo con lector de código de barras y embalaje final (Packing).
     */
    public function completeCheckingAndPacking(int $orderId, int $checkerUserId, int $packagesCount, int $coldBoxesCount, array $checkedItems): Order
    {
        $order = Order::findOrFail($orderId);

        return DB::transaction(function () use ($order, $checkerUserId, $packagesCount, $coldBoxesCount, $checkedItems) {
            foreach ($checkedItems as $item) {
                $detailId = (int) $item['order_detail_id'];
                $checkedQty = (float) $item['checked_quantity'];

                OrderDetail::where('id', $detailId)
                    ->where('order_id', $order->id)
                    ->update(['checked_quantity' => $checkedQty]);
            }

            $order->update([
                'b2b_workflow_status' => B2BOrderStatusEnum::PACKED->value,
                'assigned_checker_id' => $checkerUserId,
                'packages_count' => $packagesCount,
                'cold_boxes_count' => $coldBoxesCount,
            ]);

            return $order;
        });
    }

    /**
     * Despacha el pedido hacia la ruta de entrega y sincroniza la factura
     * de forma automática con la Farmacia si está en el ecosistema ERP.
     */
    public function dispatchOrder(int $orderId, ?int $routeId = null): Order
    {
        $order = Order::with('client')->findOrFail($orderId);

        if ($order->b2b_workflow_status !== B2BOrderStatusEnum::PACKED->value) {
            throw ValidationException::withMessages([
                'order' => 'La orden debe estar embalada (PACKED) para ser despachada.',
            ]);
        }

        return DB::transaction(function () use ($order, $routeId) {
            $order->update([
                'b2b_workflow_status' => B2BOrderStatusEnum::DISPATCHED->value,
                'delivery_route_id' => $routeId ?: $order->delivery_route_id,
            ]);

            // Si la farmacia cliente tiene vinculado un tenant ERP, precargar la factura de compra
            if ($order->client && !empty($order->client->linked_tenant_id)) {
                $this->interTenantBridge->syncInvoiceFromDrogueriaToPharmacy($order, $order->client);
            }

            return $order;
        });
    }

    /**
     * Marca la orden como entregada a la farmacia.
     */
    public function markAsDelivered(int $orderId): Order
    {
        $order = Order::findOrFail($orderId);

        $order->update([
            'b2b_workflow_status' => B2BOrderStatusEnum::DELIVERED->value,
            'status' => Order::COMPLETED,
        ]);

        return $order;
    }
}
