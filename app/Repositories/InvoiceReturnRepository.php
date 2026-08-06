<?php

namespace App\Repositories;

use App\Contracts\Repositories\InvoiceReturnRepositoryInterface;
use App\Models\InvoiceReturn;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class InvoiceReturnRepository implements InvoiceReturnRepositoryInterface
{
    /**
     * Obtener devoluciones de facturas con ordenación y filtros.
     * Devoluciones pendientes primero (de más antigua a más reciente), seguidas de aprobadas/rechazadas.
     */
    public function getPaginatedReturns(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        $query = InvoiceReturn::query()
            ->select([
                'id',
                'invoice_id',
                'product_id',
                'quantity',
                'amount_refunded',
                'supplier_discount_percentage',
                'return_date',
                'lot_number',
                'expiration_date',
                'status',
                'created_at',
            ])
            ->with([
                'invoice' => function ($q) {
                    $q->select(['id', 'invoice_number', 'supplier_id']);
                },
                'invoice.supplier' => function ($q) {
                    $q->select(['id', 'name', 'rif']);
                },
                'product' => function ($q) {
                    $q->select(['id', 'name', 'barcode', 'iva']);
                },
            ]);

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->whereHas('invoice', function ($iq) use ($search) {
                    $iq->where('invoice_number', 'like', "%{$search}%")
                        ->orWhereHas('supplier', function ($sq) use ($search) {
                            $sq->where('name', 'like', "%{$search}%");
                        });
                })->orWhereHas('product', function ($pq) use ($search) {
                    $pq->where('name', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%");
                });
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('return_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('return_date', '<=', $filters['date_to']);
        }

        // Ordenamiento optimizado: pendientes primero por antigüedad (created_at asc)
        $query->orderByRaw("
            CASE 
                WHEN status = 'pending' THEN 1 
                WHEN status = 'approved' THEN 2 
                WHEN status = 'rejected' THEN 3 
                ELSE 4 
            END ASC
        ")->orderBy('created_at', 'asc');

        return $query->paginate($perPage);
    }

    /**
     * Actualizar estado de una devolución específica.
     */
    public function updateStatus(int $returnId, string $status): InvoiceReturn
    {
        $return = InvoiceReturn::findOrFail($returnId);
        $return->status = $status;
        $return->save();

        return $return->fresh([
            'invoice' => function ($q) {
                $q->select(['id', 'invoice_number', 'supplier_id']);
            },
            'invoice.supplier' => function ($q) {
                $q->select(['id', 'name', 'rif']);
            },
            'product' => function ($q) {
                $q->select(['id', 'name', 'barcode', 'iva']);
            },
        ]);
    }

    /**
     * Actualizar estado masivo de devoluciones asociadas a una factura.
     */
    public function updateStatusByInvoice(int $invoiceId, string $status): int
    {
        return InvoiceReturn::where('invoice_id', $invoiceId)
            ->update(['status' => $status]);
    }
}
