<?php

namespace App\Repository;

use App\Models\AutoOrder;
use App\Models\AutoOrderDetail;
use App\Models\ProductSupplier;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AutoOrdersRepository
{
    public function create(array $datos): ?AutoOrder
    {
        $record = AutoOrder::create($datos);
        return $record;
    }

    public function getAll(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $perPage = (int) ($filters["itemsPerPage"] ?? $perPage);

        $query = AutoOrder::query()
            ->with(["supplier"])
            ->when($filters["selectedSupplier"] ?? null, function ($q, $supplierId) {
                return $q->where("supplier_id", $supplierId);
            });

        return $query
            ->paginate($perPage)
            ->through(fn($record) => [...$record->toArray(), "supplier_name" => $record->supplier->name ?? null]);
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
}
