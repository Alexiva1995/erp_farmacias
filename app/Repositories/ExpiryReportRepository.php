<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\ExpiryReportRepositoryInterface;
use App\Models\ProductLot;
use App\Models\ExpiredLog;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ExpiryReportRepository implements ExpiryReportRepositoryInterface
{
    public function getExpiryHorizon(array $filters): array
    {
        $query = ProductLot::query()
            ->join('products', 'product_lots.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->select(
                DB::raw("DATE_FORMAT(product_lots.expiration_date, '%Y-%m') as month"),
                'categories.name as category_name',
                DB::raw('SUM(product_lots.quantity * products.unit_cost) as total_value'),
                DB::raw('SUM(product_lots.quantity) as total_units')
            )
            ->where('product_lots.quantity', '>', 0)
            ->where('product_lots.expiration_date', '>=', now())
            ->where('product_lots.expiration_date', '<=', now()->addMonths(6))
            ->groupBy('month', 'category_name')
            ->orderBy('month');

        $this->applyFilters($query, $filters);

        return $query->get()->toArray();
    }

    public function getRealLossAnalysis(array $filters): array
    {
        // JOIN con products ya está hecho, se filtra directamente sobre la columna
        // del JOIN en lugar de usar whereHas() que genera una subconsulta correlacionada EXISTS
        $query = ExpiredLog::query()
            ->join('products', 'expired_logs.product_id', '=', 'products.id')
            ->select(
                DB::raw("DATE_FORMAT(expired_logs.created_at, '%Y-%m') as month"),
                DB::raw('SUM(expired_logs.expired_quantity) as total_units'),
                DB::raw('SUM(expired_logs.expired_quantity * products.unit_cost) as total_cost')
            )
            ->whereNull('products.deleted_at')
            ->where('products.is_deleted', false)
            ->groupBy('month')
            ->orderBy('month', 'desc')
            ->limit(6);

        // Filtro directo sobre el JOIN — sin subconsulta correlacionada
        if (!empty($filters['laboratory_id'])) {
            $query->where('products.laboratory_id', $filters['laboratory_id']);
        }

        if (!empty($filters['category_id'])) {
            $query->where('products.category_id', $filters['category_id']);
        }

        if (!empty($filters['group_id'])) {
            $query->where('products.group_id', $filters['group_id']);
        }

        return $query->get()->toArray();
    }


    public function getOverstockWarning(array $filters): array
    {
        $query = ProductLot::query()
            ->join('products', 'product_lots.product_id', '=', 'products.id')
            ->leftJoin('laboratories', 'products.laboratory_id', '=', 'laboratories.id')
            ->select(
                'products.id as product_id',
                'products.name',
                'products.barcode',
                'laboratories.name as laboratory_name',
                'product_lots.lot_number',
                'product_lots.quantity as stock_actual',
                'product_lots.expiration_date',
                'products.sales_average as venta_mensual_promedio',
                'products.unit_cost',
                DB::raw('TIMESTAMPDIFF(MONTH, NOW(), product_lots.expiration_date) as meses_restantes'),
                // Unidades en riesgo = stock actual − proyección de ventas hasta el vencimiento
                // Si es positivo → hay sobrestock en riesgo de caducar
                DB::raw('GREATEST(0, product_lots.quantity - GREATEST(0, TIMESTAMPDIFF(MONTH, NOW(), product_lots.expiration_date)) * products.sales_average) as unidades_en_riesgo')
            )
            ->where('product_lots.quantity', '>', 0)
            ->where('product_lots.expiration_date', '>=', now())
            // Solo lotes con vencimiento en menos de 12 meses
            ->where('product_lots.expiration_date', '<=', now()->addMonths(12))
            // Límite defensivo: evita traer miles de filas en inventarios grandes
            ->limit(200);

        $this->applyFilters($query, $filters);

        return $query->get()->toArray();
    }

    public function getCurrentExpiredStock(array $filters): array
    {
        $endOfMonth = now()->endOfMonth();

        $query = ProductLot::query()
            ->join('products', 'product_lots.product_id', '=', 'products.id')
            ->select(
                DB::raw('COALESCE(SUM(product_lots.quantity), 0) as total_units'),
                DB::raw('COALESCE(SUM(product_lots.quantity * products.unit_cost), 0) as total_value')
            )
            ->where('product_lots.quantity', '>', 0)
            ->where('product_lots.expiration_date', '<=', $endOfMonth);

        $this->applyFilters($query, $filters);

        $result = $query->first();

        return $result ? $result->toArray() : ['total_units' => 0, 'total_value' => 0];
    }

    private function applyFilters($query, array $filters): void
    {
        // Excluir productos eliminados (SoftDeletes e is_deleted)
        $query->whereNull('products.deleted_at')
              ->where('products.is_deleted', false);

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('products.name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('products.barcode', 'like', '%' . $filters['search'] . '%');
            });
        }

        if (!empty($filters['laboratory_id'])) {
            $query->where('products.laboratory_id', $filters['laboratory_id']);
        }

        if (!empty($filters['category_id'])) {
            $query->where('products.category_id', $filters['category_id']);
        }

        if (!empty($filters['group_id'])) {
            $query->where('products.group_id', $filters['group_id']);
        }
    }
}
