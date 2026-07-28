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
            'user:id,username,name',
            'details:id,fiscal_history_id,product_id,product_name,quantity,exempt_amount,vat_status,total_amount',
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
     * Verifica la integridad de un registro comparando su hash almacenado.
     */
    public function verifyAuditHash($history): ?bool
    {
        if (!$history->audit_hash) {
            return null;
        }

        $detailsForHash = [];
        foreach ($history->details as $detail) {
            $priceBs = $detail->vat_status == 1 
                ? ($detail->total_amount / 1.16) 
                : $detail->exempt_amount;

            $detailsForHash[] = "{$detail->product_id}:{$detail->quantity}:" . number_format((float)$priceBs, 4, '.', '');
        }

        $identOnly = preg_replace('/[^0-9]/', '', (string)$history->identification);

        $auditString = implode('|', [
            $identOnly,
            number_format((float)$history->exempt_amount, 2, '.', ''),
            number_format((float)$history->taxable_amount, 2, '.', ''),
            number_format((float)$history->iva_amount, 2, '.', ''),
            number_format((float)$history->total_amount, 2, '.', ''),
            $history->order_id,
            implode('|', $detailsForHash)
        ]);

        $calculatedHash = hash('sha256', $auditString);

        return hash_equals($history->audit_hash, $calculatedHash);
    }
}
