<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Expenses;
use App\Contracts\FixedExpenses;
use App\Contracts\Transaction;
use App\Data\CreateExpenseData;
use App\Enums\ExpensePaymentMethod;
use App\Enums\ExpenseStatus;
use App\Enums\FixedExpenseIntervalUnit;
use App\Enums\FixedExpenseOccurrenceStatus;
use App\Models\FixedExpense;
use App\Models\FixedExpenseOccurrence;
use App\Repositories\FixedExpenseRepository;
use Carbon\Carbon;
use DomainException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FixedExpenseService implements FixedExpenses
{
    // Máximo de días de aviso permitido (debe coincidir con la validación del Form Request)
    public const MAX_REMINDER_DAYS = 90;

    // Tope de cuotas generadas por plantilla en una sola ejecución (protección ante bucles)
    private const MAX_OCCURRENCES_PER_RUN = 120;

    // Campos que, si cambian, obligan a recalcular el calendario de cuotas
    private const SCHEDULE_FIELDS = ['start_date', 'interval_unit', 'interval_count', 'end_date'];

    public function __construct(
        protected FixedExpenseRepository $repository,
        protected Expenses $expenses,
        protected Transaction $transaction
    ) {
    }

    public function paginate(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        return $this->repository->paginate($filters, $perPage);
    }

    public function create(array $data): FixedExpense
    {
        return DB::transaction(function () use ($data) {
            $start = Carbon::parse($data['start_date'])->startOfDay();

            $data['day_of_month'] = $start->day;
            $data['next_due_date'] = $start->toDateString();
            $data['user_id'] = auth()->id();
            $data['is_active'] = $data['is_active'] ?? true;

            $fixedExpense = $this->repository->create($data);
            $this->generateForFixedExpense($fixedExpense, $this->today());

            return $this->repository->findOrFail($fixedExpense->id);
        });
    }

    public function update(int $id, array $data): FixedExpense
    {
        return DB::transaction(function () use ($id, $data) {
            $fixedExpense = $this->repository->findOrFail($id);
            $today = $this->today();

            $scheduleChanged = $this->hasScheduleChanged($fixedExpense, $data);

            $this->repository->update($fixedExpense, $data);

            if ($scheduleChanged) {
                $this->rebuildSchedule($fixedExpense, $today);
            } else {
                // Solo cambian datos del gasto: se actualizan las cuotas pendientes aún no vencidas
                $this->repository->syncPendingOccurrencesAmount($fixedExpense, $today);
            }

            $this->generateForFixedExpense($fixedExpense, $today);

            return $this->repository->findOrFail($fixedExpense->id);
        });
    }

    public function delete(int $id): void
    {
        DB::transaction(function () use ($id) {
            $fixedExpense = $this->repository->findOrFail($id);

            // Se conservan las cuotas pagadas/omitidas como historial
            $this->repository->deleteAllPendingOccurrences($fixedExpense);
            $this->repository->delete($fixedExpense);
        });
    }

    public function toggleActive(int $id): FixedExpense
    {
        return DB::transaction(function () use ($id) {
            $fixedExpense = $this->repository->findOrFail($id);
            $today = $this->today();

            if ($fixedExpense->is_active) {
                // Al pausar se retiran las cuotas futuras; las vencidas se mantienen visibles
                $this->repository->update($fixedExpense, ['is_active' => false]);
                $this->repository->deletePendingOccurrencesFrom($fixedExpense, $today);

                return $this->repository->findOrFail($fixedExpense->id);
            }

            // Al reactivar no se genera atraso: se salta a la primera fecha vigente desde hoy
            $next = Carbon::parse($fixedExpense->next_due_date);
            $guard = 0;
            while ($next->lt($today) && $guard < 1000) {
                $next = $this->calculateNextDueDate($fixedExpense, $next);
                $guard++;
            }

            $this->repository->update($fixedExpense, ['is_active' => true]);
            $this->repository->setNextDueDate($fixedExpense, $next);
            $this->generateForFixedExpense($fixedExpense, $today);

            return $this->repository->findOrFail($fixedExpense->id);
        });
    }

    public function generateDueOccurrences(): int
    {
        $today = $this->today();
        $candidates = $this->repository->getDueForGeneration($today->copy()->addDays(self::MAX_REMINDER_DAYS));
        $total = 0;

        foreach ($candidates as $fixedExpense) {
            try {
                $total += DB::transaction(fn () => $this->generateForFixedExpense($fixedExpense, $today));
            } catch (\Throwable $e) {
                // Un error en una plantilla no debe detener la generación del resto
                Log::error('Error generando cuotas de gasto fijo', [
                    'fixed_expense_id' => $fixedExpense->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $total;
    }

    public function paginateOccurrences(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->repository->paginateOccurrences($filters, $perPage);
    }

    public function getOccurrencesSummary(): array
    {
        $today = $this->today();
        $rows = $this->repository->pendingSummaryByCurrency($today, $today->copy()->addDays(7));

        $summary = [
            'overdue_count' => 0,
            'week_count' => 0,
            'total_count' => 0,
            'by_currency' => [],
        ];

        foreach ($rows as $row) {
            $summary['overdue_count'] += (int) $row->overdue_count;
            $summary['week_count'] += (int) $row->week_count;
            $summary['total_count'] += (int) $row->total_count;
            $summary['by_currency'][] = [
                'currency' => $row->currency,
                'overdue_amount' => round((float) $row->overdue_amount, 2),
                'week_amount' => round((float) $row->week_amount, 2),
                'total_amount' => round((float) $row->total_amount, 2),
            ];
        }

        return $summary;
    }

    public function payOccurrence(int $occurrenceId, CreateExpenseData $data): FixedExpenseOccurrence
    {
        DB::transaction(function () use ($occurrenceId, $data) {
            $occurrence = $this->repository->findOccurrenceForUpdate($occurrenceId);

            if ($occurrence->status !== FixedExpenseOccurrenceStatus::PENDING) {
                throw new DomainException('Esta cuota ya fue procesada.');
            }

            // El gasto real sigue el flujo de aprobación configurado en ExpensesServices
            $expense = $this->expenses->create($data);

            if ($expense->status === ExpenseStatus::APPROVED->value) {
                // Mismo mapeo de cuenta que en el registro manual de gastos
                $paymentMethod = ExpensePaymentMethod::fromOldLabel((string) $expense->count);
                $expense->count = $paymentMethod->value;
                $this->transaction->createTransactionSalida($expense);
            }

            $this->repository->markOccurrencePaid($occurrence, (int) $expense->id, (float) $data->total_amount, auth()->id());
        });

        return $this->repository->findOccurrence($occurrenceId);
    }

    public function skipOccurrence(int $occurrenceId, ?string $note): FixedExpenseOccurrence
    {
        DB::transaction(function () use ($occurrenceId, $note) {
            $occurrence = $this->repository->findOccurrenceForUpdate($occurrenceId);

            if ($occurrence->status !== FixedExpenseOccurrenceStatus::PENDING) {
                throw new DomainException('Solo se pueden omitir cuotas por pagar.');
            }

            $this->repository->markOccurrenceSkipped($occurrence, $note, auth()->id());
        });

        return $this->repository->findOccurrence($occurrenceId);
    }

    /**
     * Calcula la siguiente fecha de vencimiento respetando el día ancla
     * (un gasto del día 31 vence el último día en meses cortos sin desplazarse).
     */
    public function calculateNextDueDate(FixedExpense $fixedExpense, Carbon $from): Carbon
    {
        $count = max(1, (int) $fixedExpense->interval_count);
        $anchorDay = (int) ($fixedExpense->day_of_month ?: Carbon::parse($fixedExpense->start_date)->day);

        return match ($fixedExpense->interval_unit) {
            FixedExpenseIntervalUnit::DAY => $from->copy()->addDays($count),
            FixedExpenseIntervalUnit::WEEK => $from->copy()->addWeeks($count),
            FixedExpenseIntervalUnit::MONTH => $this->withAnchorDay($from->copy()->startOfMonth()->addMonthsNoOverflow($count), $anchorDay),
            FixedExpenseIntervalUnit::YEAR => $this->withAnchorDay($from->copy()->startOfMonth()->addYearsNoOverflow($count), $anchorDay),
        };
    }

    /**
     * Crea las cuotas cuya fecha ya entró en la ventana de aviso, recuperando días perdidos.
     */
    private function generateForFixedExpense(FixedExpense $fixedExpense, Carbon $today): int
    {
        if (!$fixedExpense->is_active) {
            return 0;
        }

        $limit = $today->copy()->addDays((int) $fixedExpense->reminder_days_before);
        $endDate = $fixedExpense->end_date ? Carbon::parse($fixedExpense->end_date) : null;
        $next = Carbon::parse($fixedExpense->next_due_date)->startOfDay();
        $created = 0;

        while ($next->lte($limit) && $created < self::MAX_OCCURRENCES_PER_RUN) {
            if ($endDate && $next->gt($endDate)) {
                break;
            }

            $this->repository->createOccurrenceIfMissing($fixedExpense, $next);
            $next = $this->calculateNextDueDate($fixedExpense, $next);
            $created++;
        }

        if ($created > 0) {
            $this->repository->setNextDueDate($fixedExpense, $next);
        }

        return $created;
    }

    /**
     * Recalcula el calendario tras cambiar la frecuencia o la fecha inicial.
     */
    private function rebuildSchedule(FixedExpense $fixedExpense, Carbon $today): void
    {
        $start = Carbon::parse($fixedExpense->start_date)->startOfDay();
        $fixedExpense->day_of_month = $start->day;
        $fixedExpense->save();

        // Las cuotas futuras pendientes se regeneran con el nuevo calendario
        $this->repository->deletePendingOccurrencesFrom($fixedExpense, $today);

        $last = $this->repository->lastOccurrenceDate($fixedExpense);
        $next = ($last === null || $last->lt($start)) ? $start : $this->calculateNextDueDate($fixedExpense, $last);

        $this->repository->setNextDueDate($fixedExpense, $next);
    }

    private function hasScheduleChanged(FixedExpense $fixedExpense, array $data): bool
    {
        foreach (self::SCHEDULE_FIELDS as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            $current = $fixedExpense->{$field};
            $current = $current instanceof \BackedEnum ? $current->value : $current;
            $current = $current instanceof \DateTimeInterface ? $current->format('Y-m-d') : $current;

            $incoming = $data[$field];
            if ($incoming !== null && in_array($field, ['start_date', 'end_date'], true)) {
                $incoming = Carbon::parse($incoming)->toDateString();
            }

            if ((string) $current !== (string) $incoming) {
                return true;
            }
        }

        return false;
    }

    private function withAnchorDay(Carbon $monthStart, int $anchorDay): Carbon
    {
        return $monthStart->day(min($anchorDay, $monthStart->daysInMonth));
    }

    private function today(): Carbon
    {
        return Carbon::now(config('app.timezone'))->startOfDay();
    }
}
