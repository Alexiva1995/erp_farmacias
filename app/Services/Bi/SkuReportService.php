<?php

namespace App\Services\Bi;

use App\Contracts\Repositories\SkuReportRepositoryInterface;
use Illuminate\Support\Collection;

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
            $totalCost = $unitCost * $totalSoldQty;
            
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
}
