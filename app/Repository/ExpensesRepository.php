<?php


namespace App\Repository;

use App\Data\CreateExpenseData;
use App\Data\CreateExpenseRecurrenceData;
use App\Data\EditExpenseRecurrenceData;
use App\Models\ExchangeRate;
use App\Models\Expense;
use DateTime;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use PhpOffice\PhpSpreadsheet\Shared\TimeZone;

class ExpensesRepository
{

    public function create(CreateExpenseData $data): Expense
    {
        $expenseData = $data->toArray();

        $exchangeRate = ExchangeRate::where('currency_code', 'BS')->first();
        $rate = $exchangeRate->rate;

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

        // Asegurar que expense_date sea solo la fecha sin hora
        if (isset($expenseData['expense_date']) && $expenseData['expense_date'] instanceof \DateTime) {
            $expenseData['expense_date'] = $expenseData['expense_date']->format('Y-m-d');
        }

        if (isset($expenseData['account'])) {
            $expenseData['count'] = $expenseData['account'];
            unset($expenseData['account']);
        }

        return Expense::create($expenseData);
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
        $gasto->status = "Pending";
        $gasto->save();
        return $gasto;
    }

    public function uploadInvoice(array $data): Expense
    {
        $gasto = Expense::find($data["id"]);

        if (!$gasto) {
            throw new \Exception("Gasto no encontrado");
        }

        // Handle file upload
        if (isset($data['file_invoice']) && $data['file_invoice'] instanceof \Illuminate\Http\UploadedFile) {
            $file = $data['file_invoice'];
            $fileName = 'invoice_' . time() . '_' . $gasto->id . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('expenses/invoices', $fileName, 'public');

            $gasto->file_name = $fileName;
            $gasto->extension_file = $file->getClientOriginalExtension();
            $gasto->url_file = asset('storage/' . $path);
            $gasto->date_upload = now();
            $gasto->has_invoice = true;

            $gasto->save();
        }

        return $gasto;
    }

    public function edit(array $data): Expense|null
    {
        Expense::where("id", "=", $data["id"])->update($data);
        return Expense::find($data["id"]);
    }

    public function editExpenseRecurring(EditExpenseRecurrenceData $data): Expense|null
    {
        $expense = $this->consultById($data->id);
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
        return Expense::query()->with(["user", "category"])->orderBy("name", "ASC")->get();
    }

    public function findById(string $id): ?Expense
    {
        return Expense::find($id);
    }

    public function deleteById(string $id): void
    {
        Expense::where("id", "=", $id)->delete();
    }

    public function buildFilter(array $filtros): Builder
    {
        $consulta = Expense::query()->with(["user", "category"]);

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

        if (array_key_exists("currency", $filtros)) {
            $consulta->where("currency", "=", $filtros["currency"]);
        }

        if (array_key_exists("status", $filtros)) {
            if (is_array($filtros["status"]) && count($filtros["status"]) > 0) {
                $consulta->whereIn("status", $filtros["status"]);
            }
        }

        if (array_key_exists("category_id_filtro", $filtros)) {
            $consulta->where("category_id", "=", $filtros["category_id_filtro"]);
        }

        if (array_key_exists("fechaDesde_filtro", $filtros) && array_key_exists("fechaHasta_filtro", $filtros)) {
            if ($filtros["fechaDesde_filtro"] != "" && $filtros["fechaHasta_filtro"] != "") {
                $consulta->whereBetween("expense_date", [$filtros["fechaDesde_filtro"] . " 00:00:00", $filtros["fechaHasta_filtro"] . " 23:59:59"]);
            }
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
        Expense::where("id", "=", $id)->update([
            "status" => $status
        ]);
        return Expense::find($id);
    }

    public function getAllRecurringExpensesOfToday(): Collection
    {
        $timeZone = new DateTimeZone(config("app.timezone"));
        $hoy = new DateTime('now', $timeZone);

        $consulta = Expense::query()
            ->where("type_of_expense", "=", "Recurrente")
            ->whereDate("next_expense_date", "=", $hoy->format("Y-m-d"))
            ->get();

        return $consulta;
    }

    private function convertAmountToBs($amount, $conversion_rate, $rate)
    {
        return ($amount === 0.0 ? 0.0 : $amount / $conversion_rate) * $rate;
    }
}
