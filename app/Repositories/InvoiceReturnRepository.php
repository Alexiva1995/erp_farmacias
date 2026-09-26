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
                    $q->select(['id', 'invoice_number', 'supplier_id', 'currency', 'exchange_rate']);
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
        return \Illuminate\Support\Facades\DB::transaction(function () use ($returnId, $status) {
            $return = InvoiceReturn::with(['invoice.supplier', 'product'])->findOrFail($returnId);
            $previousStatus = $return->status ? ($return->status instanceof \BackedEnum ? $return->status->value : (string) $return->status) : 'pending';

            $return->status = $status;
            $return->save();

            // Al aprobar la devolución, generar automáticamente la Nota de Débito (ND) correspondiente para el proveedor
            if ($status === 'approved' && $previousStatus !== 'approved') {
                $invoice = $return->invoice;
                $rate = (float) ($invoice?->exchange_rate ?? 1);
                $refundAmountUsd = (float) $return->amount_refunded;
                $refundAmountBs = $invoice?->currency === 'USD' || empty($invoice?->currency)
                    ? ($refundAmountUsd * ($rate > 0 ? $rate : 1))
                    : $refundAmountUsd;

                // Generar correlativo de Nota de Débito ND-DEV-{returnId} o ND-{invoice_number}-{returnId}
                $invNumber = $invoice?->invoice_number ?? 'DEV';
                $ndNumber = "ND-{$invNumber}-{$return->id}";

                \App\Models\Invoice::updateOrCreate(
                    [
                        'invoice_number' => $ndNumber,
                        'supplier_id' => $invoice?->supplier_id,
                    ],
                    [
                        'control_number' => "ND-CTRL-{$return->id}",
                        'exp_date' => now()->addDays(30)->toDateString(),
                        'payment_date' => now()->toDateString(),
                        'received_date' => now()->toDateString(),
                        'created_invoice_date' => now()->toDateString(),
                        'currency' => $invoice?->currency ?? 'USD',
                        'is_indexed' => false,
                        'exchange_rate' => $rate > 0 ? $rate : 1,
                        'exempt_amount' => 0.00,
                        'taxable_base' => 0.00,
                        'tax_amount' => 0.00,
                        'total_amount' => $refundAmountBs,
                        'total_usd' => $refundAmountUsd,
                        'net_payable_amount' => $refundAmountBs,
                        'status' => 'loaded',
                        'status_payment' => 0, // Pendiente para compensación
                        'uploaded_by' => auth()->id() ?? 1,
                        'registered_by' => auth()->id() ?? 1,
                    ]
                );
            }

            return $return->fresh([
                'invoice' => function ($q) {
                    $q->select(['id', 'invoice_number', 'supplier_id', 'currency', 'exchange_rate']);
                },
                'invoice.supplier' => function ($q) {
                    $q->select(['id', 'name', 'rif']);
                },
                'product' => function ($q) {
                    $q->select(['id', 'name', 'barcode', 'iva']);
                },
            ]);
        });
    }

    /**
     * Actualizar estado masivo de devoluciones asociadas a una factura.
     */
    public function updateStatusByInvoice(int $invoiceId, string $status): int
    {
        return InvoiceReturn::where('invoice_id', $invoiceId)
            ->update(['status' => $status]);
    }

    /**
     * Obtener estadísticas de devoluciones (totales y por estado).
     */
    public function getStats(array $filters = []): array
    {
        $query = InvoiceReturn::query();

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

        if (!empty($filters['date_from'])) {
            $query->whereDate('return_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('return_date', '<=', $filters['date_to']);
        }

        $raw = (clone $query)->selectRaw("
            COUNT(*) as total,
            COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) as pending,
            COALESCE(SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END), 0) as approved,
            COALESCE(SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END), 0) as rejected
        ")->first();

        return [
            'total' => (int) ($raw->total ?? 0),
            'pending' => (int) ($raw->pending ?? 0),
            'approved' => (int) ($raw->approved ?? 0),
            'rejected' => (int) ($raw->rejected ?? 0),
        ];
    }
}
