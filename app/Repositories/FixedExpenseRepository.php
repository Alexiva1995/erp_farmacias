<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\FixedExpenseOccurrenceStatus;
use App\Models\FixedExpense;
use App\Models\FixedExpenseOccurrence;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class FixedExpenseRepository
{
    private const FIXED_EXPENSE_COLUMNS = [
        'id', 'name', 'category_id', 'amount', 'currency', 'is_amount_variable', 'default_count',
        'interval_unit', 'interval_count', 'day_of_month', 'start_date', 'end_date', 'next_due_date',
        'reminder_days_before', 'is_active', 'notes', 'created_at', 'updated_at',
    ];

    private const OCCURRENCE_COLUMNS = [
        'fixed_expense_occurrences.id', 'fixed_expense_occurrences.fixed_expense_id',
        'fixed_expense_occurrences.due_date', 'fixed_expense_occurrences.amount',
        'fixed_expense_occurrences.currency', 'fixed_expense_occurrences.status',
        'fixed_expense_occurrences.expense_id', 'fixed_expense_occurrences.paid_at',
        'fixed_expense_occurrences.skipped_at', 'fixed_expense_occurrences.note',
    ];

    // ===== Plantillas =====

    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = FixedExpense::query()
            ->select(self::FIXED_EXPENSE_COLUMNS)
            ->with(['category:id,name']);

        if (!empty($filters['search'])) {
            $query->where('name', 'like', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        return $query->orderByDesc('is_active')->orderBy('next_due_date')->paginate($perPage);
    }

    public function findOrFail(int $id): FixedExpense
    {
        return FixedExpense::query()->with(['category:id,name'])->findOrFail($id);
    }

    public function create(array $data): FixedExpense
    {
        return FixedExpense::create($data);
    }

    public function update(FixedExpense $fixedExpense, array $data): FixedExpense
    {
        $fixedExpense->update($data);

        return $fixedExpense;
    }

    public function delete(FixedExpense $fixedExpense): void
    {
        $fixedExpense->delete();
    }

    public function setNextDueDate(FixedExpense $fixedExpense, Carbon $date): void
    {
        $fixedExpense->next_due_date = $date->toDateString();
        $fixedExpense->save();
    }

    /**
     * Gastos fijos activos candidatos a generar cuotas.
     * Se filtra por el máximo de días de aviso permitido y la ventana exacta se afina en el servicio.
     */
    public function getDueForGeneration(Carbon $limitDate): Collection
    {
        return FixedExpense::query()
            ->where('is_active', true)
            ->whereDate('next_due_date', '<=', $limitDate->toDateString())
            ->where(function ($query) {
                $query->whereNull('end_date')->orWhereColumn('next_due_date', '<=', 'end_date');
            })
            ->get();
    }

    // ===== Cuotas =====

    public function createOccurrenceIfMissing(FixedExpense $fixedExpense, Carbon $dueDate): FixedExpenseOccurrence
    {
        return FixedExpenseOccurrence::firstOrCreate(
            [
                'fixed_expense_id' => $fixedExpense->id,
                'due_date' => $dueDate->toDateString(),
            ],
            [
                'amount' => $fixedExpense->amount,
                'currency' => $fixedExpense->currency,
                'status' => FixedExpenseOccurrenceStatus::PENDING->value,
            ]
        );
    }

    public function lastOccurrenceDate(FixedExpense $fixedExpense): ?Carbon
    {
        $date = FixedExpenseOccurrence::query()
            ->where('fixed_expense_id', $fixedExpense->id)
            ->max('due_date');

        return $date ? Carbon::parse($date) : null;
    }

    /**
     * Elimina las cuotas pendientes con vencimiento desde la fecha indicada.
     */
    public function deletePendingOccurrencesFrom(FixedExpense $fixedExpense, Carbon $from): void
    {
        FixedExpenseOccurrence::query()
            ->where('fixed_expense_id', $fixedExpense->id)
            ->where('status', FixedExpenseOccurrenceStatus::PENDING->value)
            ->whereDate('due_date', '>=', $from->toDateString())
            ->delete();
    }

    public function deleteAllPendingOccurrences(FixedExpense $fixedExpense): void
    {
        FixedExpenseOccurrence::query()
            ->where('fixed_expense_id', $fixedExpense->id)
            ->where('status', FixedExpenseOccurrenceStatus::PENDING->value)
            ->delete();
    }

    /**
     * Sincroniza monto y moneda de las cuotas pendientes aún no vencidas.
     */
    public function syncPendingOccurrencesAmount(FixedExpense $fixedExpense, Carbon $from): void
    {
        FixedExpenseOccurrence::query()
            ->where('fixed_expense_id', $fixedExpense->id)
            ->where('status', FixedExpenseOccurrenceStatus::PENDING->value)
            ->whereDate('due_date', '>=', $from->toDateString())
            ->update([
                'amount' => $fixedExpense->amount,
                'currency' => $fixedExpense->currency,
            ]);
    }

    public function paginateOccurrences(array $filters, int $perPage): LengthAwarePaginator
    {
        $isHistory = ($filters['scope'] ?? 'pending') === 'history';

        $query = FixedExpenseOccurrence::query()
            ->select(self::OCCURRENCE_COLUMNS)
            ->join('fixed_expenses', 'fixed_expenses.id', '=', 'fixed_expense_occurrences.fixed_expense_id')
            ->with([
                'fixedExpense:id,name,category_id,is_amount_variable,default_count,interval_unit,interval_count,deleted_at',
                'fixedExpense.category:id,name',
                'expense:id,status',
            ]);

        if ($isHistory) {
            $query->whereIn('fixed_expense_occurrences.status', [
                FixedExpenseOccurrenceStatus::PAID->value,
                FixedExpenseOccurrenceStatus::SKIPPED->value,
            ])->orderByDesc('fixed_expense_occurrences.due_date');
        } else {
            $query->where('fixed_expense_occurrences.status', FixedExpenseOccurrenceStatus::PENDING->value)
                ->orderBy('fixed_expense_occurrences.due_date');
        }

        if (!empty($filters['search'])) {
            $query->where('fixed_expenses.name', 'like', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['category_id'])) {
            $query->where('fixed_expenses.category_id', $filters['category_id']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('fixed_expense_occurrences.due_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('fixed_expense_occurrences.due_date', '<=', $filters['date_to']);
        }

        return $query->orderBy('fixed_expense_occurrences.id')->paginate($perPage);
    }

    /**
     * Totales de cuotas pendientes agrupados por moneda y urgencia.
     */
    public function pendingSummaryByCurrency(Carbon $today, Carbon $weekLimit): Collection
    {
        $todayStr = $today->toDateString();
        $weekStr = $weekLimit->toDateString();

        return FixedExpenseOccurrence::query()
            ->where('status', FixedExpenseOccurrenceStatus::PENDING->value)
            ->selectRaw(
                'currency,
                SUM(CASE WHEN due_date < ? THEN 1 ELSE 0 END) AS overdue_count,
                SUM(CASE WHEN due_date < ? THEN amount ELSE 0 END) AS overdue_amount,
                SUM(CASE WHEN due_date >= ? AND due_date <= ? THEN 1 ELSE 0 END) AS week_count,
                SUM(CASE WHEN due_date >= ? AND due_date <= ? THEN amount ELSE 0 END) AS week_amount,
                COUNT(*) AS total_count,
                SUM(amount) AS total_amount',
                [$todayStr, $todayStr, $todayStr, $weekStr, $todayStr, $weekStr]
            )
            ->groupBy('currency')
            ->get();
    }

    public function findOccurrenceForUpdate(int $id): FixedExpenseOccurrence
    {
        return FixedExpenseOccurrence::query()->lockForUpdate()->findOrFail($id);
    }

    public function findOccurrence(int $id): FixedExpenseOccurrence
    {
        return FixedExpenseOccurrence::query()
            ->select(self::OCCURRENCE_COLUMNS)
            ->join('fixed_expenses', 'fixed_expenses.id', '=', 'fixed_expense_occurrences.fixed_expense_id')
            ->with([
                'fixedExpense:id,name,category_id,is_amount_variable,default_count,interval_unit,interval_count,deleted_at',
                'fixedExpense.category:id,name',
                'expense:id,status',
            ])
            ->where('fixed_expense_occurrences.id', $id)
            ->firstOrFail();
    }

    public function markOccurrencePaid(FixedExpenseOccurrence $occurrence, int $expenseId, float $amount, ?int $userId): void
    {
        $occurrence->update([
            'status' => FixedExpenseOccurrenceStatus::PAID->value,
            'expense_id' => $expenseId,
            'amount' => $amount,
            'paid_at' => now(),
            'paid_by_id' => $userId,
        ]);
    }

    public function markOccurrenceSkipped(FixedExpenseOccurrence $occurrence, ?string $note, ?int $userId): void
    {
        $occurrence->update([
            'status' => FixedExpenseOccurrenceStatus::SKIPPED->value,
            'skipped_at' => now(),
            'skipped_by_id' => $userId,
            'note' => $note,
        ]);
    }

    /**
     * Devuelve a "por pagar" la cuota asociada a un gasto rechazado.
     */
    public function releaseOccurrenceByExpense(int $expenseId): void
    {
        FixedExpenseOccurrence::query()
            ->where('expense_id', $expenseId)
            ->update([
                'status' => FixedExpenseOccurrenceStatus::PENDING->value,
                'expense_id' => null,
                'paid_at' => null,
                'paid_by_id' => null,
                'note' => "Pago rechazado (gasto #{$expenseId})",
            ]);
    }
}
