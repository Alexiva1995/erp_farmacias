<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Expenses;
use App\Data\CreateExpenseData;
use App\Data\CreateExpenseRecurrenceData;
use App\Data\EditExpenseRecurrenceData;
use App\Enums\ExpenseStatus;
use App\Exports\ExpenseExport;
use App\Models\Expense;
use App\Repositories\ExpensesRepository;
use DateTime;
use DateTimeZone;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExpensesServices implements Expenses
{


    public function __construct(
        protected ExpensesRepository $expensesRepository
    ) {
    }


    public function create(CreateExpenseData $data): Expense
    {
        $data->status = ExpenseStatus::PENDING->value;

        return $this->expensesRepository->create($data);
    }

    public function createRecurring(CreateExpenseRecurrenceData $data): Expense
    {
        $next_expense_date = null;
        $timeZone = new DateTimeZone(config("app.timezone"));
        $data->status = ExpenseStatus::PENDING->value;

        if ($data->recurrence === Expense::RECURRENCE_MENSUAL) {
            $next_expense_date = (new DateTime("now", $timeZone))->modify('+1 month')->format('Y-m-d');
        } elseif ($data->recurrence === Expense::RECURRENCE_ANUAL) {
            $next_expense_date = (new DateTime("now", $timeZone))->modify('+1 year')->format('Y-m-d');
        } elseif ($data->recurrence === Expense::RECURRENCE_SEMESTRAL) {
            $next_expense_date = (new DateTime("now", $timeZone))->modify('+6 months')->format('Y-m-d');
        }

        $data->next_expense_date = $next_expense_date;

        return $this->expensesRepository->createRecurring($data);
    }

    public function update(array $data): Expense
    {
        return $this->expensesRepository->edit($data);
    }

    public function findById(string $id): ?Expense
    {
        return $this->expensesRepository->findById($id);
    }

    public function getAll(): Collection
    {
        return $this->expensesRepository->getAll();
    }

    public function deleteById(string $id): void
    {
        $this->expensesRepository->deleteById($id);
    }

    public function filterWithPaginate(array $filtros, int $perPage = 10): LengthAwarePaginator
    {
        return $this->expensesRepository->filterWithPaginate($filtros, $perPage);
    }

    public function filterWithoutPaginate(array $filtros): Collection
    {
        return $this->expensesRepository->filterWithoutPaginate($filtros);
    }

    public function updateStatus(int $id, string $status): Expense
    {
        return $this->expensesRepository->updateStatus($id, $status);
    }

    public function exportToExcel(array $filters): ExpenseExport
    {
        $build = $this->expensesRepository->buildFilter($filters);
        return new ExpenseExport($build);
    }

    public function uploadInvoice(array $data): Expense
    {
        // Fecha de carga
        $timeZone = new DateTimeZone(config("app.timezone"));
        $hoy = new DateTime("now", $timeZone);
        $data["date_upload"] = $hoy->format("Y-m-d");

        // Validaciones mínimas de entrada
        /** @var UploadedFile $file */
        $file = $data["file_invoice"] ?? null;
        $id = $data["id"] ?? null;

        if (!$file instanceof UploadedFile || !$id) {
            throw new \InvalidArgumentException("Datos inválidos para cargar la factura.");
        }


        $relativeDir = $this->ensureInvoiceDirectories((string) $id);
        $meta = $this->storeInvoiceAndGetMeta($file, $relativeDir);


        $data["file_name"] = $meta["file_name"]; // solo UUID sin extensión
        $data["extension_file"] = $meta["extension_file"];
        $data["url_file"] = $meta["url_file"];

        return $this->expensesRepository->uploadInvoice($data);
    }

    private function ensureInvoiceDirectories(string $id): string
    {
        $disk = Storage::disk('public');

        $baseDir = 'facturas';
        if (!$disk->exists($baseDir)) {
            $disk->makeDirectory($baseDir);
        }

        $expenseDir = $baseDir . '/' . $id;
        if (!$disk->exists($expenseDir)) {
            $disk->makeDirectory($expenseDir);
        }

        return $expenseDir;
    }

    /**
     * Guarda el archivo en el disco public y retorna metadatos: nombre (UUID), extensión y URL pública.
     *
     * @return array{file_name:string, extension_file:string, url_file:string}
     */
    private function storeInvoiceAndGetMeta(UploadedFile $file, string $relativeDir): array
    {
        $disk = Storage::disk('public');

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $uuid = (string) Str::uuid();
        $filename = $uuid . '.' . $extension;

        // Guardar archivo
        $disk->putFileAs($relativeDir, $file, $filename);

        // Construir URL pública
        //$url = $disk->url($relativeDir . '/' . $filename);
        $url = $relativeDir . '/' . $filename;

        return [
            'file_name' => $uuid,
            'extension_file' => $extension,
            'url_file' => $url,
        ];
    }

    public function executeRecurringExpensesOfToday(): void
    {
        $expenses = $this->expensesRepository->getAllRecurringExpensesOfToday();
        Log::info("ejecutar gastos recurrentes");
        for ($index = 0; $index < count($expenses); $index++) {
            $expense = $expenses[$index];
            $timeZone = new DateTimeZone(config("app.timezone"));
            $hoy = new DateTime('now', $timeZone);

            Log::info("gastos programdo");
            Log::info($expense);

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
                "type_of_expense" => ExpenseStatus::PENDING->value, // Usando Enum o constante adecuada si aplica
                "status" => ExpenseStatus::PENDING->value,
                "amount_bs" => $expense->amount_bs,
            ]);
            $expenseNormal = $this->create($expenseNormalData);
            Log::info("gastos normal creado del recurrente");
            Log::info($expenseNormal);

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
            $expenseRecurrenteEdit = $this->expensesRepository->editExpenseRecurring($expenseRecurenteData);
            Log::info("actualización del gastos recurrente");
            Log::info($expenseRecurrenteEdit);
        }
    }

    public function getGlobalStats(array $filters): array
    {
        return $this->expensesRepository->getGlobalStats($filters);
    }
}
