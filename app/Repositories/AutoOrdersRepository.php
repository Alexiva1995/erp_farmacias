<?php

declare(strict_types=1);

namespace App\Repositories;

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

        // Ordenar siempre desde la más reciente (ID más alto) a la más antigua
        $query->orderByDesc("auto_orders.id");

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
        $query = AutoOrder::query()
            ->when(
                $filters['selectedSupplier'] ?? null,
                fn($q, $id) => $q->where('auto_orders.supplier_id', $id)
            )
            ->when(
                $filters['search'] ?? null,
                fn($q, $search) => $q->where('auto_orders.id', 'like', "%{$search}%")
            )
            ->when(
                $filters['start_date'] ?? null,
                fn($q, $date) => $q->whereDate('auto_orders.order_date', '>=', $date)
            )
            ->when(
                $filters['end_date'] ?? null,
                fn($q, $date) => $q->whereDate('auto_orders.order_date', '<=', $date)
            );

        $stats = $query->selectRaw('
            COUNT(*) as total_orders,
            COALESCE(SUM(total_amount), 0) as total_amount,
            COUNT(CASE WHEN status = 0 THEN 1 END) as pending_orders,
            COUNT(CASE WHEN status = 1 THEN 1 END) as sent_orders,
            COUNT(CASE WHEN status = 2 THEN 1 END) as completed_orders
        ')->first();

        return [
            'total_orders' => (int) ($stats->total_orders ?? 0),
            'total_amount' => (float) ($stats->total_amount ?? 0),
            'pending_orders' => (int) ($stats->pending_orders ?? 0),
            'sent_orders' => (int) ($stats->sent_orders ?? 0),
            'completed_orders' => (int) ($stats->completed_orders ?? 0),
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
            return $this->finish($autoOrder);
        }
        
        return false;
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
                \App\Models\ProductSupplier::whereIn('id', $productSupplierIds)->delete();
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
