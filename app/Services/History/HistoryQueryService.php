<?php

declare(strict_types=1);

namespace App\Services\History;

use App\Models\FiscalHistory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class HistoryQueryService
{
    /**
     * Prepara la consulta base para FiscalHistory.
     */
    private function getBaseQuery(): Builder
    {
        return FiscalHistory::select([
            'id',
            'fiscal_id',
            'order_id',
            'invoice_number',
            'identification',
            'business_name',
            'address',
            'invoice_date',
            'exempt_amount',
            'taxable_amount',
            'iva_amount',
            'total_amount',
            'audit_hash',
            'user_id',
        ])->with([
            'user:id,username',
            'details:id,fiscal_history_id,product_id,product_name,quantity,exempt_amount,vat_status,total_amount,iva_amount',
            'order.details.product',
        ]);
    }

    /**
     * Aplica los filtros a la consulta de productos.
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        if (!empty($filters['q'])) {
            $searchTerm = "%{$filters['q']}%";
            $query->where(function ($subQuery) use ($searchTerm) {
                $subQuery->where('id', 'like', $searchTerm)
                    ->orWhere('invoice_number', 'like', $searchTerm)
                    ->orWhere('business_name', 'like', $searchTerm);
            });
        }

        // Filtro por fecha de factura - Solo desde 2026 en adelante
        $query->where('invoice_date', '>=', '2026-01-01');

        if (!empty($filters['startDate'])) {
            $query->whereDate('invoice_date', '>=', $filters['startDate']);
        }

        if (!empty($filters['endDate'])) {
            $query->whereDate('invoice_date', '<=', $filters['endDate']);
        }

        return $query;
    }

    /**
     * Aplica la ordenación a la consulta de productos.
     */
    private function applySorting(Builder $query, ?string $sortBy, string $orderBy): Builder
    {
        $validSortColumns = [
            'id',
            'fiscal_id',
            'invoice_number',
            'identification',
            'business_name',
            'invoice_date',
            'exempt_amount',
            'iva_amount',
            'total_amount',
        ];

        if (in_array($sortBy, $validSortColumns)) {
            return $query->orderBy($sortBy, $orderBy);
        }

        return $query->orderBy('invoice_date', 'desc');
    }

    /**
     * Método público principal que obtiene el constructor de consultas preparado.
     */
    public function getFilteredQuery(Request $request): Builder
    {
        $query = $this->getBaseQuery();

        $filters = [
            'q' => $request->q,
            'startDate' => $request->startDate,
            'endDate' => $request->endDate,
        ];

        $this->applyFilters($query, $filters);
        $this->applySorting($query, $request->input('sortBy'), $request->input('orderBy', 'asc'));

        return $query;
    }

    /**
     * Obtiene agregados de resumen fiscal (Exento, IVA, Total) para los filtros aplicados.
     */
    public function getSummaryStats(Request $request): array
    {
        $baseQuery = FiscalHistory::query();
        
        $filters = [
            'q' => $request->q,
            'startDate' => $request->startDate,
            'endDate' => $request->endDate,
        ];

        $this->applyFilters($baseQuery, $filters);

        $stats = $baseQuery->selectRaw('
            COUNT(*) as total_count,
            COALESCE(SUM(exempt_amount), 0) as total_exempt,
            COALESCE(SUM(iva_amount), 0) as total_iva,
            COALESCE(SUM(total_amount), 0) as grand_total
        ')->first();

        return [
            'total_count'  => (int) ($stats->total_count ?? 0),
            'total_exempt' => (float) ($stats->total_exempt ?? 0),
            'total_iva'    => (float) ($stats->total_iva ?? 0),
            'grand_total'  => (float) ($stats->grand_total ?? 0),
        ];
    }

    /**
     * Verifica la integridad de un registro comparando su hash almacenado.
     */
    public function verifyAuditHash($history): ?bool
    {
        if (!$history->audit_hash) {
            return null;
        }

        $identOnly = preg_replace('/[^0-9]/', '', (string)$history->identification);
        $exemptStr = number_format((float)$history->exempt_amount, 2, '.', '');
        $taxableStr = number_format((float)$history->taxable_amount, 2, '.', '');
        $ivaStr = number_format((float)$history->iva_amount, 2, '.', '');
        $totalStr = number_format((float)$history->total_amount, 2, '.', '');

        $detailCombinations = [];

        // Combinación A: Usar datos de fiscal_history_details (neto = total - iva o exento)
        $detailsA = [];
        foreach ($history->details as $detail) {
            if ($detail->vat_status == 1) {
                $unitNet = (float)$detail->total_amount - (float)$detail->iva_amount;
            } else {
                $unitNet = (float)$detail->exempt_amount;
            }
            $productId = $detail->product_id ?? 'dish_' . $detail->id;
            $detailsA[] = "{$productId}:{$detail->quantity}:" . number_format((float)$unitNet, 4, '.', '');
        }
        $detailCombinations[] = implode('|', $detailsA);

        // Combinación B: Dividir entre cantidad si fue registrado el monto total
        $detailsB = [];
        foreach ($history->details as $detail) {
            $qty = (float)$detail->quantity ?: 1.0;
            if ($detail->vat_status == 1) {
                $unitNet = ((float)$detail->total_amount - (float)$detail->iva_amount) / $qty;
            } else {
                $unitNet = (float)$detail->exempt_amount / $qty;
            }
            $productId = $detail->product_id ?? 'dish_' . $detail->id;
            $detailsB[] = "{$productId}:{$detail->quantity}:" . number_format((float)$unitNet, 4, '.', '');
        }
        $detailCombinations[] = implode('|', $detailsB);

        // Combinación C: Usar total_amount / 1.16
        $detailsC = [];
        foreach ($history->details as $detail) {
            $qty = (float)$detail->quantity ?: 1.0;
            $unitNet = $detail->vat_status == 1 ? ((float)$detail->total_amount / 1.16) : (float)$detail->exempt_amount;
            $productId = $detail->product_id ?? 'dish_' . $detail->id;
            $detailsC[] = "{$productId}:{$detail->quantity}:" . number_format((float)$unitNet, 4, '.', '');
        }
        $detailCombinations[] = implode('|', $detailsC);

        // Combinación D: Usar detalles originales de la orden de venta si está disponible
        if ($history->order && $history->order->relationLoaded('details')) {
            $detailsD = [];
            foreach ($history->order->details as $detail) {
                if ($detail->product_type === 'dish') {
                    $priceBs = (float)($detail->price_bs ?? $detail->price);
                    $detailsD[] = "dish_{$detail->dish_id}:{$detail->quantity}:" . number_format($priceBs, 4, '.', '');
                } elseif ($detail->product_type === 'court') {
                    $priceBs = (float)($detail->price_bs ?? $detail->price);
                    $detailsD[] = "court_{$detail->court_id}:{$detail->quantity}:" . number_format($priceBs, 4, '.', '');
                } elseif ($detail->product) {
                    $discount = (float)($detail->discount_percentage ?? 0);
                    $priceBs = (float)($detail->price_bs ?? ($detail->product->price_bs * (1 - ($discount / 100))));
                    if ($detail->product->iva == 1) {
                        $priceBs = $priceBs / 1.16;
                    }
                    $detailsD[] = "{$detail->product->id}:{$detail->quantity}:" . number_format($priceBs, 4, '.', '');
                }
            }
            if (!empty($detailsD)) {
                $detailCombinations[] = implode('|', $detailsD);
            }
        }

        foreach ($detailCombinations as $detailsStr) {
            $auditString = implode('|', [
                $identOnly,
                $exemptStr,
                $taxableStr,
                $ivaStr,
                $totalStr,
                $history->order_id,
                $detailsStr
            ]);

            if (hash_equals($history->audit_hash, hash('sha256', $auditString))) {
                return true;
            }
        }

        return false;
    }
}
