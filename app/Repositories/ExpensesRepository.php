<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Expenses;
use App\Data\CreateExpenseData;
use App\Data\CreateExpenseRecurrenceData;
use App\Data\EditExpenseRecurrenceData;
use App\Exports\ExpenseExport;
use App\Models\ExchangeRate;
use App\Models\Expense;
use App\Models\ExpenseAudit;
use DateTime;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ExpensesRepository implements Expenses
{
    public function create(CreateExpenseData $data): Expense
    {
        $expenseData = $data->toArray();

        $exchangeRate = ExchangeRate::where('currency_code', 'BS')->first();
        $rate = $exchangeRate?->rate ?? 1.0;

        $expenseData['amount'] = $expenseData['total_amount'];
        if ($expenseData['currency'] === 'USD') {
            $expenseData['conversion_rate'] = 1.0000;
            $expenseData['exempt_amount'] = $rate * $expenseData['exempt_amount'];
            $expenseData['taxable_base'] = $rate * $expenseData['taxable_base'];
            $expenseData['tax_amount'] = $rate * $expenseData['tax_amount'];
            $expenseData['total_usd'] = $expenseData['amount'];
            $expenseData['exchange_rate'] = $rate;
        } else {
            $expenseData['conversion_rate'] = $expenseData['exchange_rate'];
        }

        if ($expenseData['currency'] === 'COP') {
            $expenseData['exempt_amount'] = $this->convertAmountToBs($expenseData['exempt_amount'], $expenseData['conversion_rate'], $rate);
            $expenseData['taxable_base'] = $this->convertAmountToBs($expenseData['taxable_base'], $expenseData['conversion_rate'], $rate);
            $expenseData['tax_amount'] = $this->convertAmountToBs($expenseData['tax_amount'], $expenseData['conversion_rate'], $rate);
            $expenseData['total_usd'] = $expenseData['amount_usd'];
            $expenseData['exchange_rate'] = $rate;
            $expenseData['amount_bs'] = $expenseData['amount_usd'] * $rate;
        }

        if (isset($expenseData['expense_date']) && $expenseData['expense_date'] instanceof DateTime) {
            $expenseData['expense_date'] = $expenseData['expense_date']->format('Y-m-d');
        }

        if (isset($expenseData['account'])) {
            $expenseData['count'] = $expenseData['account'];
            unset($expenseData['account']);
        }

        $expense = Expense::create($expenseData);

        ExpenseAudit::create([
            'expense_id' => $expense->id,
            'user_id' => auth()->id() ?? $expense->user_id,
            'action' => 'created',
            'old_values' => null,
            'new_values' => $expense->only(['name', 'amount', 'total_usd', 'currency', 'status', 'expense_date']),
            'ip_address' => request()->ip(),
        ]);

        return $expense;
    }

    public function createRecurring(CreateExpenseRecurrenceData $data): Expense
    {
        $gasto = new Expense();
        $gasto->name = $data->name;
        $gasto->category_id = $data->category_id;
        $gasto->amount = $data->amount;
        $gasto->amount_usd = $data->amount_usd;
        $gasto->currency = $data->currency;
        $gasto->has_invoice = $data->has_invoice;
        $gasto->is_deductible = $data->is_deductible;

        if ($data->is_deductible) {
            $gasto->exchange_rate = $data->conversion_rate;
        }

        $gasto->iva = $data->iva;
        $gasto->user_id = $data->user_id;
        $gasto->count = $data->count;
        $gasto->type_of_expense = $data->type_of_expense;
        $gasto->recurrence = $data->recurrence;
        $gasto->next_expense_date = $data->next_expense_date;
        $gasto->amount_bs = $data->amount_bs;
        $gasto->status = Expense::STATUS_PENDING;
        $gasto->save();

        ExpenseAudit::create([
            'expense_id' => $gasto->id,
            'user_id' => auth()->id() ?? $gasto->user_id,
            'action' => 'created_recurring',
            'old_values' => null,
            'new_values' => $gasto->only(['name', 'amount', 'currency', 'recurrence']),
            'ip_address' => request()->ip(),
        ]);

        return $gasto;
    }

    public function uploadInvoice(array $data): Expense
    {
        $gasto = Expense::find($data["id"]);

        if (!$gasto) {
            throw new \Exception("Gasto no encontrado");
        }

        if (isset($data['file_invoice']) && $data['file_invoice'] instanceof \Illuminate\Http\UploadedFile) {
            $file = $data['file_invoice'];
            $fileName = 'invoice_' . time() . '_' . $gasto->id . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('expenses/invoices', $fileName, 'public');

            $oldFile = $gasto->url_file;

            $gasto->file_name = $fileName;
            $gasto->extension_file = $file->getClientOriginalExtension();
            $gasto->url_file = asset('storage/' . $path);
            $gasto->date_upload = now();
            $gasto->has_invoice = true;

            $gasto->save();

            ExpenseAudit::create([
                'expense_id' => $gasto->id,
                'user_id' => auth()->id(),
                'action' => 'invoice_uploaded',
                'old_values' => ['url_file' => $oldFile],
                'new_values' => ['url_file' => $gasto->url_file, 'file_name' => $fileName],
                'ip_address' => request()->ip(),
            ]);
        }

        return $gasto;
    }

    public function edit(array $data): ?Expense
    {
        $expense = Expense::find($data["id"]);
        if (!$expense) {
            return null;
        }

        $oldValues = $expense->only(['name', 'amount', 'total_usd', 'category_id', 'currency', 'expense_date']);
        $expense->update($data);

        ExpenseAudit::create([
            'expense_id' => $expense->id,
            'user_id' => auth()->id(),
            'action' => 'updated',
            'old_values' => $oldValues,
            'new_values' => $expense->fresh()->only(['name', 'amount', 'total_usd', 'category_id', 'currency', 'expense_date']),
            'ip_address' => request()->ip(),
        ]);

        return $expense;
    }

    public function editExpenseRecurring(EditExpenseRecurrenceData $data): ?Expense
    {
        $expense = $this->findById((string)$data->id);
        if (!$expense) {
            return null;
        }
        $expense->name = $data->name;
        $expense->category_id = $data->category_id;
        $expense->amount = $data->amount;
        $expense->amount_usd = $data->amount_usd;
        $expense->currency = $data->currency;
        $expense->has_invoice = $data->has_invoice;
        $expense->is_deductible = $data->is_deductible;
        $expense->iva = $data->iva;
        $expense->user_id = $data->user_id;
        $expense->count = $data->count;
        $expense->type_of_expense = $data->type_of_expense;
        $expense->recurrence = $data->recurrence;
        $expense->next_expense_date = $data->next_expense_date;
        $expense->status = $data->status;
        $expense->amount_bs = $data->amount_bs;
        $expense->save();
        return $expense;
    }

    public function getAll(): Collection
    {
        return Expense::query()->with(["user", "category", "approvedBy", "cancelledBy", "audits.user"])->orderBy("name", "ASC")->get();
    }

    public function findById(string $id): ?Expense
    {
        return Expense::with(["user", "category", "approvedBy", "cancelledBy", "audits.user"])->find($id);
    }

    public function deleteById(string $id): void
    {
        Expense::where("id", "=", $id)->delete();
    }

    public function buildFilter(array $filtros): Builder
    {
        $consulta = Expense::query()->with(["user", "category", "approvedBy", "cancelledBy", "audits.user"]);

        $consulta->orderBy('id', 'desc');

        if (array_key_exists("buscardor_filtro", $filtros)) {
            if ($filtros["buscardor_filtro"] != "") {
                $consulta->where(function ($query) use ($filtros) {
                    $query->where("name", "like", "%" . $filtros["buscardor_filtro"] . "%")
                        ->orWhere("id", "like", "%" . $filtros["buscardor_filtro"] . "%");
                });
            }
        }

        if (array_key_exists("count", $filtros)) {
            $consulta->where("count", "=", $filtros["count"]);
        }

        if (!empty($filtros["currency"])) {
            $consulta->where("currency", "=", $filtros["currency"]);
        }

        if (array_key_exists("status", $filtros)) {
            if (is_array($filtros["status"]) && count($filtros["status"]) > 0) {
                $consulta->whereIn("status", $filtros["status"]);
            }
        }

        if (!empty($filtros["category_id_filtro"])) {
            $consulta->where("category_id", "=", $filtros["category_id_filtro"]);
        }

        if (!empty($filtros["fechaDesde_filtro"]) && !empty($filtros["fechaHasta_filtro"])) {
            $consulta->whereBetween("expense_date", [
                $filtros["fechaDesde_filtro"] . " 00:00:00",
                $filtros["fechaHasta_filtro"] . " 23:59:59"
            ]);
        }

        if (array_key_exists("sortBy", $filtros) && array_key_exists("orderBy", $filtros)) {
            $consulta->orderBy($filtros["sortBy"], $filtros["orderBy"]);
        } else {
            $consulta->orderBy("name", "ASC");
        }

        if (array_key_exists("hasInvoice", $filtros)) {
            $value = filter_var($filtros["hasInvoice"], FILTER_VALIDATE_BOOLEAN);

            if ($value) {
                $consulta->where("has_invoice", 1);
            } else {
                $consulta->where(function ($query) {
                    $query->where("has_invoice", 0)
                        ->orWhereNull("has_invoice");
                });
            }
        }

        if (array_key_exists("isDeductible", $filtros)) {
            if ($filtros["isDeductible"] === 1 || $filtros["isDeductible"] === true) {
                $consulta->where("is_deductible", 1);
            } elseif ($filtros["isDeductible"] === 0 || $filtros["isDeductible"] === false) {
                $consulta->where("is_deductible", 0);
            }
        }

        return $consulta;
    }

    public function filterWithPaginate(array $filtros, int $perPage = 10): LengthAwarePaginator
    {
        $consulta = $this->buildFilter($filtros);

        return $consulta->paginate($perPage);
    }

    public function filterWithoutPaginate(array $filtros): Collection
    {
        $consulta = $this->buildFilter($filtros);

        return $consulta->get();
    }

    public function updateStatus(int $id, string $status): Expense
    {
        $expense = Expense::find($id);
        if (!$expense) {
            throw new \Exception("Gasto no encontrado");
        }

        $oldStatus = $expense->status;
        $userId = auth()->id();
        $now = now();

        $updateData = ["status" => $status];

        if ($status === Expense::STATUS_APPROVED) {
            $updateData['approved_by_id'] = $userId;
            $updateData['approved_at'] = $now;
        } elseif ($status === Expense::STATUS_CANCELLED) {
            $updateData['cancelled_by_id'] = $userId;
            $updateData['cancelled_at'] = $now;
        }

        $expense->update($updateData);

        ExpenseAudit::create([
            'expense_id' => $expense->id,
            'user_id' => $userId,
            'action' => 'status_changed',
            'old_values' => ['status' => $oldStatus],
            'new_values' => ['status' => $status],
            'ip_address' => request()->ip(),
        ]);

        return $expense->fresh();
    }

    public function getAllRecurringExpensesOfToday(): Collection
    {
        $timeZone = new DateTimeZone(config("app.timezone"));
        $hoy = new DateTime('now', $timeZone);

        return Expense::query()
            ->where("type_of_expense", "=", "Recurrente")
            ->whereDate("next_expense_date", "=", $hoy->format("Y-m-d"))
            ->get();
    }

    public function getGlobalStats(array $filters): array
    {
        $baseQuery = $this->buildFilter($filters);
        
        $stats = $baseQuery->selectRaw('status, COUNT(*) as total, SUM(total_usd) as amount')
            ->setEagerLoads([])
            ->reorder()
            ->groupBy('status')
            ->get();

        return [
            'totalApproved' => (int) ($stats->where('status', 'Approved')->first()?->total ?? 0),
            'amountApproved' => (float) ($stats->where('status', 'Approved')->first()?->amount ?? 0),
            'totalPending' => (int) ($stats->where('status', 'Pending')->first()?->total ?? 0),
            'amountPending' => (float) ($stats->where('status', 'Pending')->first()?->amount ?? 0),
            'totalCancelled' => (int) ($stats->where('status', 'Cancelled')->first()?->total ?? 0),
            'amountCancelled' => (float) ($stats->where('status', 'Cancelled')->first()?->amount ?? 0),
        ];
    }

    private function convertAmountToBs($amount, $conversion_rate, $rate)
    {
        return ($amount === 0.0 ? 0.0 : $amount / $conversion_rate) * $rate;
    }

    public function update(array $data): Expense
    {
        return $this->edit($data);
    }

    public function exportToExcel(array $filters): ExpenseExport
    {
        $build = $this->buildFilter($filters);
        return new ExpenseExport($build);
    }

    public function executeRecurringExpensesOfToday(): void
    {
        $expenses = $this->getAllRecurringExpensesOfToday();
        foreach ($expenses as $expense) {
            $timeZone = new DateTimeZone(config("app.timezone"));
            $hoy = new DateTime('now', $timeZone);

            $expenseNormalData = CreateExpenseData::from([
                "name" => $expense->name,
                "category_id" => $expense->category_id,
                "amount" => $expense->amount,
                "amount_usd" => $expense->amount_usd,
                "currency" => $expense->currency,
                "has_invoice" => $expense->has_invoice,
                "is_deductible" => $expense->is_deductible,
                "iva" => $expense->iva,
                "expense_date" => $hoy->format('Y-m-d'),
                "user_id" => $expense->user_id,
                "account" => $expense->count,
                "type_of_expense" => Expense::STATUS_PENDING,
                "status" => Expense::STATUS_PENDING,
                "amount_bs" => $expense->amount_bs,
            ]);
            $this->create($expenseNormalData);

            $next_expense_date = null;
            if ($expense->recurrence === Expense::RECURRENCE_MENSUAL) {
                $next_expense_date = (new DateTime("now", $timeZone))->modify('+1 month')->format('Y-m-d');
            } elseif ($expense->recurrence === Expense::RECURRENCE_ANUAL) {
                $next_expense_date = (new DateTime("now", $timeZone))->modify('+1 year')->format('Y-m-d');
            } elseif ($expense->recurrence === Expense::RECURRENCE_SEMESTRAL) {
                $next_expense_date = (new DateTime("now", $timeZone))->modify('+6 months')->format('Y-m-d');
            }

            $expenseRecurenteData = EditExpenseRecurrenceData::from([
                "id" => $expense->id,
                "name" => $expense->name,
                "category_id" => $expense->category_id,
                "amount" => $expense->amount,
                "amount_usd" => $expense->amount_usd,
                "currency" => $expense->currency,
                "has_invoice" => $expense->has_invoice,
                "is_deductible" => $expense->is_deductible,
                "iva" => $expense->iva,
                "user_id" => $expense->user_id,
                "count" => $expense->count,
                "type_of_expense" => Expense::TYPE_OF_EXPENSE_RECURRENTE,
                "recurrence" => $expense->recurrence,
                "next_expense_date" => $next_expense_date,
                "status" => "Pending",
                "amount_bs" => $expense->amount_bs,
            ]);
            $this->editExpenseRecurring($expenseRecurenteData);
        }
    }
}
