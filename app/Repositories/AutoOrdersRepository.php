<?php

declare(strict_types=1);

namespace App\Repositories;

use App\AutoOrderDetailStatus;
use App\Enums\AutoOrderStatus;
use App\Models\AutoOrder;
use App\Models\AutoOrderDetail;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AutoOrdersRepository
{
    public function baseQuery()
    {
        return AutoOrder::query()
            ->with(['supplier.connections'])
            ->select(
                'auto_orders.id',
                'auto_orders.supplier_id',
                'auto_orders.status',
                'auto_orders.order_date',
                'auto_orders.tentative_delivery_date',
                'auto_orders.hash_token',
                'auto_orders.total_quantity',
                'auto_orders.total_amount',
                'suppliers.name as supplier_name',
                'suppliers.sales_phone as phone'
            )
            ->join('suppliers', 'auto_orders.supplier_id', '=', 'suppliers.id');
    }

    public function applyFilters($query, array $filters = [])
    {
        $perPage = $filters["itemsPerPage"] ?? 10;
        if ($perPage <= 0) {
            $perPage = 999999;
        }
        $supplierId = $filters["selectedSupplier"] ?? null;
        $search = $filters["search"] ?? null;
        $startDate = $filters["start_date"] ?? null;
        $endDate = $filters["end_date"] ?? null;

        if ($supplierId) {
            $query->where("auto_orders.supplier_id", $supplierId);
        }

        if ($search) {
            $query->where("auto_orders.id", "like", "%{$search}%");
        }

        if ($startDate) {
            $query->whereDate("auto_orders.order_date", ">=", $startDate);
        }

        if ($endDate) {
            $query->whereDate("auto_orders.order_date", "<=", $endDate);
        }

        // Ordenamiento dinámico
        $sortBy = $filters['sortBy'] ?? 'id';
        $sortOrder = strtolower((string) ($filters['sortOrder'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        $sortableColumns = [
            'id'                      => 'auto_orders.id',
            'supplier_name'           => 'suppliers.name',
            'total_quantity'          => 'auto_orders.total_quantity',
            'total_amount'            => 'auto_orders.total_amount',
            'status'                  => 'auto_orders.status',
            'order_date'              => 'auto_orders.order_date',
            'tentative_delivery_date' => 'auto_orders.tentative_delivery_date',
            'created_at'              => 'auto_orders.created_at',
        ];

        $orderColumn = $sortableColumns[$sortBy] ?? 'auto_orders.id';
        $query->orderBy($orderColumn, $sortOrder);

        return $query->paginate($perPage);
    }

    public function create(array $datos): ?AutoOrder
    {
        // Asegurar que total_quantity sea un entero
        if (isset($datos['total_quantity'])) {
            $datos['total_quantity'] = (int) $datos['total_quantity'];
        }
        // Asegurar que total_items sea un entero
        if (isset($datos['total_items'])) {
            $datos['total_items'] = (int) $datos['total_items'];
        }
        $record = AutoOrder::create($datos);
        return $record;
    }

    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $filters["itemsPerPage"] ??= 10;

        // Sincronizar y auto-finalizar órdenes enviadas según facturas y tiempos
        if (!isset($filters['status']) || (int)$filters['status'] === 1 || (int)$filters['status'] === 2) {
            $this->syncSentOrdersForSupplier($filters['selectedSupplier'] ?? null);
        }

        $query = $this->baseQuery();

        // Filtrar por status si se proporciona (valores del Enum: 0=PENDING, 1=SENT, 2=COMPLETED)
        if (isset($filters['status']) && $filters['status'] !== null && $filters['status'] !== '') {
            $query->where('auto_orders.status', (int) $filters['status']);
        }

        return $this->applyFilters($query, $filters);
    }

    public function delete(AutoOrder $autoOrder)
    {
        if ($autoOrder) {
            $autoOrder->delete();
            return true;
        }

        return false;
    }

    public function update(AutoOrder $autoOrder, $data)
    {
        $whensQty = "";
        $whensCost = "";
        $whensSub = "";
        $whensUpd = "";
        $ids = [];

        foreach ($data["details"] as $row) {
            $id = (int) $row["id"];
            $qty = (float) $row["quantity"];
            $cost = (float) $row["unit_cost"];
            $subtotal = $qty * $cost;
            $ids[] = $id;

            $whensQty .= "WHEN {$id} THEN {$qty} ";
            $whensCost .= "WHEN {$id} THEN {$cost} ";
            $whensSub .= "WHEN {$id} THEN {$subtotal} ";
            $whensUpd .= "WHEN {$id} THEN NOW() ";
        }

        $idsList = implode(",", $ids);

        try {
            $affected = DB::transaction(function () use ($autoOrder, $whensQty, $whensCost, $whensSub, $whensUpd, $idsList) {
                $total = DB::affectingStatement(
                    "
                    UPDATE auto_order_details
                    SET
                        quantity   = CASE id {$whensQty} END,
                        unit_cost  = CASE id {$whensCost} END,
                        subtotal   = CASE id {$whensSub} END,
                        updated_at = CASE id {$whensUpd} END
                    WHERE order_id = ?
                      AND id IN ({$idsList})
                      AND deleted_at IS NULL
                ",
                    [$autoOrder->id],
                );

                DB::affectingStatement(
                    "
                    UPDATE auto_orders
                    SET
                        total_items =
                            (SELECT COUNT(*)
                            FROM auto_order_details
                            WHERE order_id = ?
                                AND deleted_at IS NULL),
                        total_quantity =
                           ( SELECT COALESCE(SUM(quantity), 0)
                            FROM auto_order_details
                            WHERE order_id = ?
                            AND deleted_at IS NULL),
                        total_amount =
                            (SELECT COALESCE(SUM(quantity * unit_cost), 0)
                            FROM auto_order_details
                            WHERE order_id = ?
                            AND deleted_at IS NULL)
                    WHERE id = ?
                ",
                    [$autoOrder->id, $autoOrder->id, $autoOrder->id, $autoOrder->id],
                );

                return $total;
            });

            return [
                "status" => "ok",
                "count" => $affected,
            ];
        } catch (QueryException $e) {
            return [
                "status" => "error",
                "count" => 0,
            ];
        }
    }

    public function getHistory(array $filters = [])
    {
        $stats = DB::table("auto_order_details")
            ->select([
                "order_id",
                DB::raw(
                    "ROUND(100.0 * SUM(status = " .
                    AutoOrderDetailStatus::ARRIVED->value .
                    ") / NULLIF(COUNT(*), 0), 2) AS percentage",
                ),
            ])
            ->groupBy("order_id");

        $query = AutoOrder::query()
            ->select(["auto_orders.*", "suppliers.name as supplier_name", "stats.percentage as percentage_arrived"])
            ->join("suppliers", "suppliers.id", "=", "auto_orders.supplier_id")
            ->leftJoinSub($stats, "stats", fn($join) => $join->on("stats.order_id", "=", "auto_orders.id"))
            ->whereIn("auto_orders.status", [AutoOrderStatus::SENT, AutoOrderStatus::COMPLETED])
            ->orderByDesc("auto_orders.created_at");

        return $this->applyFilters($query, $filters);
    }

    public function getExportableData(AutoOrder $autoOrder)
    {
        $rate = \App\Models\ExchangeRate::where('currency_code', 'BS')
            ->orderByDesc('created_at')
            ->value('rate') ?? 1.0;

        $query = DB::table("auto_order_details")
            ->select([
                DB::raw("COALESCE(product_suppliers.name, products.name) as product_name"),
                "auto_order_details.quantity",
                DB::raw("COALESCE(product_suppliers.cod_supplier, product_suppliers.barcode_match, products.barcode, '') as cod"),
                DB::raw("COALESCE(
                    CASE 
                        WHEN auto_order_details.unit_cost = product_suppliers.unit_cost_usd_with_discount THEN COALESCE(product_suppliers.unit_cost_with_discount, product_suppliers.unit_cost)
                        WHEN auto_order_details.unit_cost = product_suppliers.unit_cost_usd THEN product_suppliers.unit_cost
                        ELSE auto_order_details.unit_cost * {$rate}
                    END,
                    auto_order_details.unit_cost * {$rate}
                ) as unit_cost_bs"),
                "auto_order_details.unit_cost as unit_cost",
            ])
            ->leftJoin("product_suppliers", "product_suppliers.id", "=", "auto_order_details.product_suppliers_id")
            ->leftJoin("products", "products.id", "=", "auto_order_details.product_id")
            ->where("auto_order_details.order_id", $autoOrder->id)
            ->get();

        return $query;
    }

    public function getStats(array $filters = [])
    {
        $hasCustomDate = !empty($filters['start_date']) || !empty($filters['end_date']);
        $startOfMonth = Carbon::now()->startOfMonth()->toDateString();
        $endOfMonth = Carbon::now()->endOfMonth()->toDateString();

        $baseQuery = AutoOrder::query()
            ->when(
                $filters['selectedSupplier'] ?? null,
                fn($q, $id) => $q->where('auto_orders.supplier_id', $id)
            )
            ->when(
                $filters['search'] ?? null,
                fn($q, $search) => $q->where('auto_orders.id', 'like', "%{$search}%")
            );

        // Órdenes pendientes y enviadas (totales acumulados o según rango si se especificó)
        $statusQuery = (clone $baseQuery)
            ->when(
                $filters['start_date'] ?? null,
                fn($q, $date) => $q->whereDate('auto_orders.order_date', '>=', $date)
            )
            ->when(
                $filters['end_date'] ?? null,
                fn($q, $date) => $q->whereDate('auto_orders.order_date', '<=', $date)
            );

        $pendingOrders = (clone $statusQuery)->where('status', 0)->count();
        $sentOrders = (clone $statusQuery)->where('status', 1)->count();
        $totalOrders = (clone $statusQuery)->count();

        // Completadas e Inversión Total: Por defecto "lo que va de mes"
        $monthQuery = (clone $baseQuery);
        if ($hasCustomDate) {
            $monthQuery->when(
                $filters['start_date'] ?? null,
                fn($q, $date) => $q->whereDate('auto_orders.order_date', '>=', $date)
            )->when(
                $filters['end_date'] ?? null,
                fn($q, $date) => $q->whereDate('auto_orders.order_date', '<=', $date)
            );
        } else {
            $monthQuery->whereDate('auto_orders.order_date', '>=', $startOfMonth)
                       ->whereDate('auto_orders.order_date', '<=', $endOfMonth);
        }

        $completedOrders = (clone $monthQuery)->where('status', 2)->count();
        $totalAmount = (clone $monthQuery)->where('status', 2)->sum('total_amount');

        return [
            'total_orders'     => (int) $totalOrders,
            'total_amount'     => (float) $totalAmount,
            'pending_orders'   => (int) $pendingOrders,
            'sent_orders'      => (int) $sentOrders,
            'completed_orders' => (int) $completedOrders,
        ];
    }

    public function confirmSent(AutoOrder $autoOrder): bool
    {
        $sentAt = now();
        $tentativeDate = null;
        $supplier = $autoOrder->supplier;

        if ($supplier && !empty($supplier->dispatch_days)) {
            $dispatchDays = is_string($supplier->dispatch_days) ? json_decode($supplier->dispatch_days, true) : $supplier->dispatch_days;
            
            if (!empty($dispatchDays)) {
                $dayOfWeekMap = [
                    'sunday'    => 0,
                    'monday'    => 1,
                    'tuesday'   => 2,
                    'wednesday' => 3,
                    'thursday'  => 4,
                    'friday'    => 5,
                    'saturday'  => 6,
                ];

                $dispatchIndices = array_map(fn($d) => $dayOfWeekMap[strtolower($d)], $dispatchDays);
                sort($dispatchIndices);

                $currentDayIndex = (int) $sentAt->format('w');

                // Buscar el siguiente día de despacho
                $nextDayIndex = null;
                foreach ($dispatchIndices as $index) {
                    if ($index >= $currentDayIndex) {
                        $nextDayIndex = $index;
                        break;
                    }
                }

                if ($nextDayIndex === null) {
                    $nextDayIndex = $dispatchIndices[0];
                    $daysToAdd = 7 - $currentDayIndex + $nextDayIndex;
                } else {
                    $daysToAdd = $nextDayIndex - $currentDayIndex;
                }

                $tentativeDate = $sentAt->copy()->addDays($daysToAdd);
            }
        }

        return $autoOrder->update([
            'sent_at' => $sentAt,
            'status' => AutoOrderStatus::SENT,
            'tentative_delivery_date' => $tentativeDate
        ]);
    }

    public function checkAndCompleteOrder(AutoOrder $autoOrder): bool
    {
        return $this->checkAndAutoFinalize($autoOrder);
    }

    public function checkAndAutoFinalize(AutoOrder $autoOrder): bool
    {
        // Solo aplica a órdenes en estado ENVIADA (status = 1)
        $statusValue = is_object($autoOrder->status) ? $autoOrder->status->value : (int) $autoOrder->status;
        if ($statusValue !== AutoOrderStatus::SENT->value && $statusValue !== 1) {
            return false;
        }

        $supplierId = $autoOrder->supplier_id;
        if (!$supplierId) {
            return false;
        }

        // 1. Verificar si al menos algún producto de la orden ya fue recibido
        $hasReceivedProducts = $autoOrder->details()->where('received', 1)->exists();
        if (!$hasReceivedProducts) {
            return false;
        }

        // Si ya todos los detalles fueron procesados (ninguno con received === null)
        $pendingDetailsCount = $autoOrder->details()->whereNull('received')->count();
        if ($pendingDetailsCount === 0) {
            return $this->finish($autoOrder);
        }

        // 2. Evaluar si se cumple alguna de las 3 condiciones de auto-cierre:
        $shouldFinalize = false;

        // Condición A: Han pasado más de 20 días desde el envío o creación de la orden
        $referenceDate = $autoOrder->sent_at ?? $autoOrder->order_date ?? $autoOrder->created_at;
        if ($referenceDate && Carbon::parse($referenceDate)->diffInDays(now()) >= 20) {
            $shouldFinalize = true;
        }

        // Condición B: Ya existe una orden posterior del mismo proveedor que ya fue COMPLETADA
        if (!$shouldFinalize) {
            $hasSubsequentCompletedOrder = AutoOrder::where('supplier_id', $supplierId)
                ->where('id', '>', $autoOrder->id)
                ->where('status', AutoOrderStatus::COMPLETED->value)
                ->exists();

            if ($hasSubsequentCompletedOrder) {
                $shouldFinalize = true;
            }
        }

        // Condición C: Ya no hay más facturas de ese proveedor ni en pendientes ('pending') ni en cargadas ('loaded', 'to_order')
        if (!$shouldFinalize) {
            $hasPendingOrLoadedInvoices = \App\Models\Invoice::where('supplier_id', $supplierId)
                ->whereIn('status', ['pending', 'loaded', 'to_order'])
                ->where(function ($q) use ($autoOrder) {
                    $q->where('created_at', '>=', $autoOrder->created_at)
                      ->orWhere('auto_order_id', $autoOrder->id);
                })
                ->exists();

            if (!$hasPendingOrLoadedInvoices) {
                $shouldFinalize = true;
            }
        }

        if ($shouldFinalize) {
            // Rechazar automáticamente todo lo que no llegó
            $autoOrder->details()->whereNull('received')->update([
                'received' => 0,
                'status'   => \App\AutoOrderDetailStatus::NOT_ARRIVED->value,
            ]);

            // Finalizar la orden
            return $this->finish($autoOrder);
        }

        return false;
    }

    public function syncSentOrdersForSupplier(?int $supplierId = null): void
    {
        $query = AutoOrder::where('status', AutoOrderStatus::SENT->value);
        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        $sentOrders = $query->get();

        foreach ($sentOrders as $order) {
            // Sincronizar productos recibidos desde facturas cargadas / procesadas
            $receivedProductIds = \App\Models\InvoiceDetail::whereHas('invoice', function ($q) use ($order) {
                $q->where('supplier_id', $order->supplier_id)
                    ->whereIn('status', ['loaded', 'to_order', 'ordered', 'registered'])
                    ->where(function ($sub) use ($order) {
                        $sub->where('created_at', '>=', $order->created_at)
                            ->orWhere('auto_order_id', $order->id);
                    });
            })->pluck('product_id')->unique()->toArray();

            if (!empty($receivedProductIds)) {
                $pendingDetails = AutoOrderDetail::where('order_id', $order->id)
                    ->whereNull('received')
                    ->with(['productSupplier'])
                    ->get();

                foreach ($pendingDetails as $detail) {
                    $productId = $detail->product_id ?? $detail->productSupplier?->product_id;
                    if ($productId && in_array($productId, $receivedProductIds)) {
                        $detail->update([
                            'received' => 1,
                            'status'   => \App\AutoOrderDetailStatus::ARRIVED->value,
                        ]);
                    }
                }
            }

            // Evaluar auto-finalización
            $this->checkAndAutoFinalize($order);
        }
    }
    public function finish(AutoOrder $autoOrder): bool
    {
        return DB::transaction(function () use ($autoOrder) {
            // Eliminar ÚNICAMENTE los registros de product_suppliers asociados explícitamente a esta orden por su ID
            $productSupplierIds = $autoOrder->details()
                ->whereNotNull('product_suppliers_id')
                ->pluck('product_suppliers_id')
                ->filter()
                ->unique()
                ->toArray();

            if (!empty($productSupplierIds)) {
                // Desvincular el producto del proveedor (product_id = null) para que no vuelva a aparecer en listas/IA,
                // manteniendo la integridad referencial NOT NULL de auto_order_details sin borrar el detalle en cascada.
                \App\Models\ProductSupplier::whereIn('id', $productSupplierIds)
                    ->update(['product_id' => null]);
            }

            return $autoOrder->update(['status' => AutoOrderStatus::COMPLETED]);
        });
    }
    public function rejectPendingDetails(AutoOrder $autoOrder): void
    {
        $autoOrder->details()->whereNull('received')->update([
            'received' => 0,
            'status' => \App\AutoOrderDetailStatus::NOT_ARRIVED->value
        ]);
        
        $this->checkAndCompleteOrder($autoOrder);
    }

    public function revertToSent(AutoOrder $autoOrder): bool
    {
        return DB::transaction(function () use ($autoOrder) {
            // Revertir estado de la orden a ENVIADA (1)
            $autoOrder->update([
                'status' => AutoOrderStatus::SENT
            ]);

            // Revertir todos sus detalles a PENDIENTES (status = 0, received = null)
            $autoOrder->details()->update([
                'status' => \App\AutoOrderDetailStatus::PENDING->value,
                'received' => null
            ]);

            return true;
        });
    }
}
