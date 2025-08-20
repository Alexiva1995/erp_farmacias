<?php

namespace App\Repository;

use App\AutoOrderDetailStatus;
use App\Models\AutoOrder;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AutoOrdersRepository
{
    public function baseQuery()
    {
        return AutoOrder::query()
            ->select(["auto_orders.*", "suppliers.name as supplier_name"])
            ->join("suppliers", "auto_orders.supplier_id", "=", "suppliers.id")
            ->when($filters["selectedSupplier"] ?? null, function ($q, $supplierId) {
                return $q->where("supplier_id", $supplierId);
            });
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
        $record = AutoOrder::create($datos);
        return $record;
    }

    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $filters["itemsPerPage"] ??= 10;

        $query = $this->baseQuery();

        return $this->applyFilters($query, $filters);
    }

    public function delete(AutoOrder $autoOrder)
    {
        $autoOrder->delete();
        return response()->json(["status" => "ok"]);
    }

    public function update(AutoOrder $autoOrder, $data)
    {
        if (!isset($data)) {
            return response()->json(["status" => "error", "message" => "No hay datos proporcionados"]);
        }

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
        $filters["itemsPerPage"] ??= 10;

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
            ->where("auto_orders.status", 1)
            ->orderByDesc("auto_orders.created_at");

        return $this->applyFilters($query, $filters);
    }
}
