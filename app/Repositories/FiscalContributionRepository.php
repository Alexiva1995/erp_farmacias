<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\FiscalContributionContract;
use App\Models\FiscalContribution;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class FiscalContributionRepository implements FiscalContributionContract
{
    /**
     * Obtiene el listado paginado y filtrado de contribuciones fiscales.
     */
    public function getPaginated(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = FiscalContribution::with(['creator:id,username'])
            ->select([
                'id',
                'period',
                'tax_type',
                'document_number',
                'operation_date',
                'due_date',
                'amount',
                'status',
                'payment_date',
                'payment_reference',
                'source',
                'notes',
                'created_by',
                'created_at',
            ]);

        // Filtro por término de búsqueda (Documento, Impuesto, Periodo, Referencia)
        if (!empty($filters['search'])) {
            $search = trim((string)$filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('document_number', 'like', "%{$search}%")
                  ->orWhere('tax_type', 'like', "%{$search}%")
                  ->orWhere('period', 'like', "%{$search}%")
                  ->orWhere('payment_reference', 'like', "%{$search}%");
            });
        }

        // Filtro por tipo de impuesto
        if (!empty($filters['tax_type'])) {
            $query->where('tax_type', $filters['tax_type']);
        }

        // Filtro por periodo (ej. 09/2026)
        if (!empty($filters['period'])) {
            $query->where('period', $filters['period']);
        }

        // Filtro por estado (pending, paid, expired)
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'expired') {
                $query->where('status', 'pending')
                      ->whereDate('due_date', '<', now()->toDateString());
            } else {
                $query->where('status', $filters['status']);
            }
        }

        // Filtro por rango de fechas (Fecha de Operación o Vencimiento)
        if (!empty($filters['start_date'])) {
            $query->whereDate('operation_date', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('operation_date', '<=', $filters['end_date']);
        }

        // Ordenamiento seguro
        $sortBy = $filters['sortBy'] ?? 'due_date';
        $orderBy = $filters['orderBy'] ?? 'asc';
        $validSortColumns = ['id', 'period', 'tax_type', 'document_number', 'operation_date', 'due_date', 'amount', 'status', 'payment_date'];

        if (!in_array($sortBy, $validSortColumns, true)) {
            $sortBy = 'due_date';
        }

        return $query->orderBy($sortBy, $orderBy)->paginate($perPage);
    }

    /**
     * Obtiene las métricas y KPIs de compromisos fiscales.
     */
    public function getKpis(array $filters): array
    {
        $query = FiscalContribution::query();

        if (!empty($filters['period'])) {
            $query->where('period', $filters['period']);
        }

        if (!empty($filters['start_date'])) {
            $query->whereDate('operation_date', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('operation_date', '<=', $filters['end_date']);
        }

        $all = $query->get(['id', 'amount', 'status', 'due_date', 'payment_date']);
        $today = now()->toDateString();

        $totalPendingAmount = 0.0;
        $totalPendingCount = 0;
        $totalExpiredAmount = 0.0;
        $totalExpiredCount = 0;
        $totalPaidAmount = 0.0;
        $totalPaidCount = 0;
        $nextDueDate = null;

        foreach ($all as $item) {
            $amount = (float)$item->amount;
            $isPaid = $item->status === 'paid';
            $dueDateStr = optional($item->due_date)->format('Y-m-d');
            $isExpired = !$isPaid && $dueDateStr && $dueDateStr < $today;

            if ($isPaid) {
                $totalPaidAmount += $amount;
                $totalPaidCount++;
            } else {
                $totalPendingAmount += $amount;
                $totalPendingCount++;

                if ($isExpired) {
                    $totalExpiredAmount += $amount;
                    $totalExpiredCount++;
                }

                if ($dueDateStr && $dueDateStr >= $today) {
                    if ($nextDueDate === null || $dueDateStr < $nextDueDate) {
                        $nextDueDate = $dueDateStr;
                    }
                }
            }
        }

        return [
            'total_pending_amount' => round($totalPendingAmount, 2),
            'total_pending_count'  => $totalPendingCount,
            'total_expired_amount' => round($totalExpiredAmount, 2),
            'total_expired_count'  => $totalExpiredCount,
            'total_paid_amount'    => round($totalPaidAmount, 2),
            'total_paid_count'     => $totalPaidCount,
            'next_due_date'        => $nextDueDate,
        ];
    }

    /**
     * Crea una nueva contribución fiscal individual.
     */
    public function create(array $data): FiscalContribution
    {
        return FiscalContribution::create($data);
    }

    /**
     * Actualiza una contribución fiscal existente.
     */
    public function update(int $id, array $data): FiscalContribution
    {
        $contribution = FiscalContribution::findOrFail($id);
        $contribution->update($data);
        return $contribution->fresh();
    }

    /**
     * Elimina una contribución fiscal.
     */
    public function delete(int $id): bool
    {
        $contribution = FiscalContribution::findOrFail($id);
        return (bool)$contribution->delete();
    }

    /**
     * Importa o actualiza en lote múltiples contribuciones (Smart Paste o Bot).
     */
    public function batchUpsert(array $items, string $source = 'smart_paste', ?int $userId = null): array
    {
        $inserted = 0;
        $updated = 0;

        foreach ($items as $row) {
            $documentNumber = trim((string)($row['document_number'] ?? ''));
            $taxType = strtoupper(trim((string)($row['tax_type'] ?? '')));
            $period = trim((string)($row['period'] ?? ''));

            if (empty($documentNumber) || empty($taxType) || empty($period)) {
                continue;
            }

            $payload = [
                'period'           => $period,
                'tax_type'         => $taxType,
                'document_number'  => $documentNumber,
                'operation_date'   => $row['operation_date'],
                'due_date'         => $row['due_date'],
                'amount'           => (float)($row['amount'] ?? 0),
                'status'           => $row['status'] ?? 'pending',
                'source'           => $source,
                'notes'            => $row['notes'] ?? null,
                'created_by'       => $userId,
            ];

            $existing = FiscalContribution::where('tax_type', $taxType)
                ->where('document_number', $documentNumber)
                ->where('period', $period)
                ->first();

            if ($existing) {
                $existing->update([
                    'operation_date' => $payload['operation_date'],
                    'due_date'       => $payload['due_date'],
                    'amount'         => $payload['amount'],
                    'source'         => $source,
                ]);
                $updated++;
            } else {
                FiscalContribution::create($payload);
                $inserted++;
            }
        }

        return [
            'inserted' => $inserted,
            'updated'  => $updated,
            'total'    => $inserted + $updated,
        ];
    }

    /**
     * Alterna o establece el estado de pago de una contribución.
     */
    public function togglePaymentStatus(int $id, array $paymentData): FiscalContribution
    {
        $contribution = FiscalContribution::findOrFail($id);

        $newStatus = $paymentData['status'] ?? ($contribution->status === 'paid' ? 'pending' : 'paid');

        $updateData = [
            'status' => $newStatus,
        ];

        if ($newStatus === 'paid') {
            $updateData['payment_date'] = $paymentData['payment_date'] ?? now()->toDateString();
            $updateData['payment_reference'] = $paymentData['payment_reference'] ?? $contribution->payment_reference;
        } else {
            $updateData['payment_date'] = null;
            $updateData['payment_reference'] = null;
        }

        $contribution->update($updateData);
        return $contribution->fresh();
    }
}
