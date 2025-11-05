<?php


namespace App\Repository;

use App\Data\CreateExpenseData;
use App\Data\CreateExpenseRecurrenceData;
use App\Data\EditExpenseRecurrenceData;
use App\Models\Expense;
use DateTime;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use PhpOffice\PhpSpreadsheet\Shared\TimeZone;

class ExpensesRepository
{

    public function createGasto(CreateExpenseData $data): Expense
    {
        return Expense::create($data->toArray());
    }

    public function createGastoRecurente(CreateExpenseRecurrenceData $data): Expense
    {
        $gasto = new Expense();
        $gasto->name = $data->name;
        $gasto->category_id = $data->category_id;
        $gasto->amount = $data->amount;
        $gasto->amount_usd = $data->amount_usd;
        $gasto->currency = $data->currency;
        $gasto->has_invoice = $data->has_invoice;
        $gasto->is_deductible = $data->is_deductible;
        $gasto->iva = $data->iva;
        // $gasto->expense_date = $data->expense_date;
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

    public function cargarFactura(array $data): Expense
    {
        $gasto = Expense::find($data["id"]);

        $gasto->file_name = $data["file_name"];
        $gasto->extension_file = $data["extension_file"];
        $gasto->url_file = $data["url_file"];
        $gasto->date_upload = $data["date_upload"];

        $gasto->save();

        return $gasto;
    }

    public function edit(array $data): Expense | null
    {
        Expense::where("id", "=", $data["id"])->update($data);
        return Expense::find($data["id"]);
    }

    public function editExpenseRecurring(EditExpenseRecurrenceData $data): Expense | null
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

    public function consultAll(): Collection
    {
        return Expense::query()->with(["user", "category"])->orderBy("name", "ASC")->get();
    }

    public function consultById(string $id): ?Expense
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

        if (array_key_exists("buscardor_filtro", $filtros)) {
            if ($filtros["buscardor_filtro"] != "") {
                $consulta->where(function ($query) use ($filtros) {
                    $query->where("name", "like", "%" . $filtros["buscardor_filtro"] . "%")
                        ->orWhere("id", "like", "%" . $filtros["buscardor_filtro"] . "%");
                });
            }
        }

        if (array_key_exists("type_of_expense", $filtros)) {
            if (count($filtros) > 0) {
                $consulta->whereIn("type_of_expense", $filtros["type_of_expense"]);
            }
        }

        if (array_key_exists("count", $filtros)) {
            $consulta->where("count", "=", $filtros["count"]);
        }

        if (array_key_exists("currency", $filtros)) {
            $consulta->where("currency", "=", $filtros["currency"]);
        }

        if (array_key_exists("status", $filtros)) {
            if (count($filtros) > 0) {
                $consulta->whereIn("status", $filtros["status"]);
            }
        }

        if (array_key_exists("category_id_filtro", $filtros)) {
            $consulta->where("category_id", "=", $filtros["category_id_filtro"]);
        }

        if (array_key_exists("fechaDesde_filtro", $filtros) && array_key_exists("fechaHasta_filtro", $filtros)) {
            if ($filtros["fechaDesde_filtro"] != "" && $filtros["fechaHasta_filtro"] != "") {
                $consulta->whereBetween("created_at", [$filtros["fechaDesde_filtro"] . " 00:00:00", $filtros["fechaHasta_filtro"] . " 23:59:59"]);
            }
        }

        if (array_key_exists("sortBy", $filtros) && array_key_exists("orderBy", $filtros)) {
            $consulta->orderBy($filtros["sortBy"], $filtros["orderBy"]);
        } else {
            $consulta->orderBy("name", "ASC");
        }


        if (array_key_exists("hasInvoice", $filtros) && $filtros["hasInvoice"] === 1) {
            $consulta->where("has_invoice", 1);
        }

        if (array_key_exists("isDeductible", $filtros) && $filtros["isDeductible"] === 1) {
            $consulta->where("is_deductible", 1);
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


    public function changeStatus(int $id, string $status): Expense
    {
        Expense::where("id", "=", $id)->update([
            "status" => $status
        ]);
        return Expense::find($id);
    }

    public function consultAllExpensesRecurringOfToday(): Collection
    {
        $timezone = new DateTimeZone(env("APP_TIMEZONE"));
        $hoy = new DateTime('now', $timezone);

        $consulta = Expense::query()
            ->where("type_of_expense", "=", "Recurrente")
            ->whereDate("next_expense_date", "=", $hoy->format("Y-m-d"))
            ->get();

        return $consulta;
    }
}
