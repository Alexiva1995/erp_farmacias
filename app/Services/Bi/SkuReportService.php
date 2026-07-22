<?php

declare(strict_types=1);

namespace App\Services\Bi;

use App\Contracts\Repositories\SkuReportRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SkuReportService
{
    protected $skuReportRepository;

    public function __construct(SkuReportRepositoryInterface $skuReportRepository)
    {
        $this->skuReportRepository = $skuReportRepository;
    }

    /**
     * Genera el reporte de Margen Real calculando las 4 capas
     */
    public function generateReport(array $filters, $perPage = 15)
    {
        // Traer base query paginada
        $query = $this->skuReportRepository->getBaseQuery($filters);

        // Opcional: Sorting
        if (!empty($filters['sortBy']) && !empty($filters['orderBy'])) {
             // Si el sort es por un campo calculado (como real_margin), no se puede hacer en SQL directo de esta forma sencilla,
             // habría que ordenar la colección después. Pero soportamos orden SQL básico:
             $allowedDbSorts = ['total_sold', 'product_name'];
             if(in_array($filters['sortBy'], $allowedDbSorts)) {
                 $query->orderBy($filters['sortBy'], $filters['orderBy']);
             }
        } else {
             $query->orderBy('total_revenue', 'desc');
        }

        $paginated = $query->paginate($perPage);

        // Traer datos de pérdidas (Capa 3) solo para los períodos
        // Nota: para que sea exacto y rápido, filtramos por los IDs recolectados
        $productIds = collect($paginated->items())->pluck('product_id')->toArray();
        if(empty($productIds)) {
            return $paginated;
        }

        $filters['product_ids'] = $productIds;
        $expiredData = $this->skuReportRepository->getExpiredProducts($filters);
        // $returnedData = $this->skuReportRepository->getReturnedProducts($filters);

        // Procesar y calcular capas
        $calculatedItems = collect($paginated->items())->map(function ($item) use ($expiredData) {
            
            // --- CAPA 1: MARGEN BRUTO ---
            $unitCost = (float) $item->current_cost;
            $listPrice = (float) $item->list_price;
            $totalSoldQty = (float) $item->total_sold;
            
            $grossMarginUnit = $listPrice - $unitCost;
            $grossMarginTotal = $grossMarginUnit * $totalSoldQty;
            $grossMarginPercent = $listPrice > 0 ? ($grossMarginUnit / $listPrice) * 100 : 0;

            // --- CAPA 2: MARGEN NETO DE VENTA ---
            $totalRevenue = (float) $item->total_revenue; // Real cobrado
            // Aquí la magia principal: usamos el costo histórico del momento exacto de la venta (order_details), 
            // no el current_cost del maestro de productos de hoy.
            $totalCost = (float) $item->total_historical_cost;
            
            $netMarginTotal = $totalRevenue - $totalCost;
            $netMarginPercent = $totalRevenue > 0 ? ($netMarginTotal / $totalRevenue) * 100 : 0;
            
            // Descuento promedio aplicado
            $discountAvgAmount = $item->total_discount_amount > 0 && $totalSoldQty > 0 
                ? ($item->total_discount_amount / $totalSoldQty) : 0;
            $discountAvgPercent = $listPrice > 0 ? ($discountAvgAmount / $listPrice) * 100 : 0;

            // --- CAPA 3: MARGEN OPERATIVO (Perdidas SKU) ---
            $expiredInfo = $expiredData->get($item->product_id);
            $lossValue = $expiredInfo ? (float) $expiredInfo->total_expired_cost : 0;
            
            $realMarginTotal = $netMarginTotal - $lossValue;
            $realMarginPercent = $totalRevenue > 0 ? ($realMarginTotal / $totalRevenue) * 100 : 0;

            // --- CAPA 4: SEMÁFORO DEL MARGEN REAL ---
            $semaphoreColor = 'negro'; // Default negativo
            if ($realMarginPercent > 25) {
                $semaphoreColor = 'verde';
            } elseif ($realMarginPercent >= 10 && $realMarginPercent <= 25) {
                $semaphoreColor = 'amarillo';
            } elseif ($realMarginPercent >= 0 && $realMarginPercent < 10) {
                $semaphoreColor = 'rojo';
            }

            // Inyectar datos calculados al item para devolver
            $item->gross_margin_value = $grossMarginTotal;
            $item->gross_margin_percent = $grossMarginPercent;
            
            $item->discount_avg_percent = $discountAvgPercent;

            $item->net_margin_value = $netMarginTotal;
            $item->net_margin_percent = $netMarginPercent;

            $item->loss_value = $lossValue;

            $item->real_margin_value = $realMarginTotal;
            $item->real_margin_percent = $realMarginPercent;
            
            $item->semaphore = $semaphoreColor;

            return $item;
        });

        // Ordenamiento en memoria si se solicitó un campo calculado
        if (!empty($filters['sortBy']) && in_array($filters['sortBy'], ['real_margin_percent', 'gross_margin_percent'])) {
            $descending = ($filters['orderBy'] ?? 'desc') === 'desc';
            $calculatedItems = $calculatedItems->sortBy($filters['sortBy'], SORT_REGULAR, $descending)->values();
        }

        // Reconstruimos la paginación con los nuevos ítems
        $paginated->setCollection($calculatedItems);

        return $paginated;
    }

    /**
     * Calcula los resúmenes financieros globales de toda la consulta (sin paginar) de manera optimizada.
     */
    public function getGlobalSummary(array $filters): array
    {
        // Ejecutamos agregaciones eficientes a nivel de base de datos a partir del Query base del repositorio
        $baseQuery = $this->skuReportRepository->getBaseQuery($filters);
        
        // Obtenemos los totales agregados directamente desde SQL
        $totals = DB::table(DB::raw("({$baseQuery->toSql()}) as sub"))
            ->mergeBindings($baseQuery->getQuery())
            ->select([
                DB::raw('SUM(total_revenue) as total_revenue'),
                DB::raw('SUM(total_historical_cost) as total_historical_cost'),
                DB::raw('SUM(total_discount_amount) as total_discounts'),
            ])
            ->first();

        $totalRevenue = $totals ? (float) $totals->total_revenue : 0.0;
        $totalHistoricalCost = $totals ? (float) $totals->total_historical_cost : 0.0;
        $totalDiscountAmount = $totals ? (float) $totals->total_discounts : 0.0;

        // Para pérdidas/mermas
        $expiredData = $this->skuReportRepository->getExpiredProducts($filters);
        $totalLosses = (float) $expiredData->sum('total_expired_cost');

        $netMarginTotal = $totalRevenue - $totalHistoricalCost;
        $globalMarginNet = $totalRevenue > 0 ? ($netMarginTotal / $totalRevenue) * 100 : 0;
        
        $realMarginTotal = $netMarginTotal - $totalLosses;
        $globalMarginReal = $totalRevenue > 0 ? ($realMarginTotal / $totalRevenue) * 100 : 0;

        // Contar SKUs críticos en pérdida directa (Capa 4 Semáforo < 10%)
        // Hacemos una subquery limpia para los conteos críticos
        $criticalSkus = 0;
        $allBaseItems = $baseQuery->get();
        if ($allBaseItems->isNotEmpty()) {
            $productIds = $allBaseItems->pluck('product_id')->toArray();
            $filters['product_ids'] = $productIds;
            $expiredForCritical = $this->skuReportRepository->getExpiredProducts($filters);
            
            $criticalSkus = $allBaseItems->filter(function ($item) use ($expiredForCritical) {
                $expiredInfo = $expiredForCritical->get($item->product_id);
                $lossValue = $expiredInfo ? (float) $expiredInfo->total_expired_cost : 0;
                $netMarginTotal = (float)$item->total_revenue - (float)$item->total_historical_cost;
                $realMarginTotal = $netMarginTotal - $lossValue;
                $realMarginPercent = $item->total_revenue > 0 ? ($realMarginTotal / $item->total_revenue) * 100 : 0;
                return $realMarginPercent < 10;
            })->count();
        }

        return [
            'total_revenue' => $totalRevenue,
            'total_loss' => $totalLosses,
            'total_discounts' => $totalDiscountAmount,
            'critical_skus' => $criticalSkus,
            'global_margin_net' => $globalMarginNet,
            'global_margin_real' => $globalMarginReal
        ];
    }
}
