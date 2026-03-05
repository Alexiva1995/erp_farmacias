<?php

namespace App\Repository;

use App\AutoOrderDetailStatus;
use App\Enums\AutoOrderStatus;
use App\Models\AutoOrder;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AutoOrdersRepository
{
    public function baseQuery()
    {
        return AutoOrder::query()
            ->select(
                'auto_orders.id',
                'auto_orders.status',
                'auto_orders.order_date',
                'auto_orders.tentative_delivery_date',
                'suppliers.name as supplier_name',
                'suppliers.sales_phone as phone',
                DB::raw('SUM(auto_order_details.quantity) as total_quantity'),
                DB::raw('SUM(auto_order_details.subtotal) as total_amount')
            )
            ->join('suppliers', 'auto_orders.supplier_id', '=', 'suppliers.id')
            ->leftJoin('auto_order_details', 'auto_order_details.order_id', '=', 'auto_orders.id')
            ->groupBy(
                'auto_orders.id',
                'auto_orders.status',
                'auto_orders.order_date',
                'auto_orders.tentative_delivery_date',
                'suppliers.name',
                'suppliers.sales_phone'
            );
    }

    public function applyFilters($query, array $filters = [])
    {
        $perPage = $filters["itemsPerPage"];
        $supplierId = $filters["selectedSupplier"] ?? null;

        if ($supplierId) {
            $query->where("supplier_id", $supplierId);
        }

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
        $whensSub = "";
        $whensUpd = "";
        $ids = [];

        foreach ($data["details"] as $row) {
            $id = (int) $row["id"];
            $qty = (float) $row["quantity"];
            $subtotal = $qty * (float) $row["unit_cost"];
            $ids[] = $id;

            $whensQty .= "WHEN {$id} THEN {$qty} ";
            $whensSub .= "WHEN {$id} THEN {$subtotal} ";
            $whensUpd .= "WHEN {$id} THEN NOW() ";
        }

        $idsList = implode(",", $ids);

        try {
            $affected = DB::transaction(function () use ($autoOrder, $whensQty, $whensSub, $whensUpd, $idsList) {
                $total = DB::affectingStatement(
                    "
                    UPDATE auto_order_details
                    SET
                        quantity   = CASE id {$whensQty} END,
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
        $query = DB::table("auto_order_details")
            ->select([
                DB::raw("TRIM(BOTH '\"' FROM product_suppliers.name) as product_name"),
                "auto_order_details.quantity",
                "product_suppliers.cod_supplier as cod",
                "product_suppliers.unit_cost as unit_cost_bs",
                "product_suppliers.unit_cost_usd as unit_cost",
            ])
            ->leftJoin("product_suppliers", "product_suppliers.id", "=", "auto_order_details.product_suppliers_id")
            ->where("auto_order_details.order_id", $autoOrder->id)
            ->get();

        return $query;
    }

    public function getStats(array $filters = [])
    {
        $query = AutoOrder::query()
            ->when(
                $filters['selectedSupplier'] ?? null,
                fn($q, $id) => $q->where('auto_orders.supplier_id', $id)
            );

        return [
            'total_orders' => (clone $query)->count(),
            'total_amount' => (clone $query)->sum('total_amount') ?? 0,
            'pending_orders' => (clone $query)->where('status', AutoOrderStatus::PENDING)->count(),
            'sent_orders' => (clone $query)->where('status', AutoOrderStatus::SENT)->count(),
            'completed_orders' => (clone $query)->where('status', AutoOrderStatus::COMPLETED)->count(),
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
        // Verificar si todos los detalles tienen estado != PENDING (received no nulo)
        $allProcessed = $autoOrder->details()
            ->whereNull('received')
            ->count() === 0;

        if ($allProcessed && $autoOrder->status === AutoOrderStatus::SENT) {
            return $autoOrder->update(['status' => AutoOrderStatus::COMPLETED]);
        }
        
        return false;
    }
    public function finish(AutoOrder $autoOrder): bool
    {
        return $autoOrder->update(['status' => AutoOrderStatus::COMPLETED]);
    }
}
