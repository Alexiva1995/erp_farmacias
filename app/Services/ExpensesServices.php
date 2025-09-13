<?php

namespace App\Services;

use App\Contracts\Expenses;
use App\Exports\ExpenseExport;
use App\Models\Expense;
use App\Repository\ExpensesRepository;
use DateTime;
use DateTimeZone;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExpensesServices implements Expenses
{


    public function __construct(
        protected ExpensesRepository $expensesRepository
    ) {}


    public function crearGasto(array $data): Expense
    {
        $data["status"] = "Pending";
        return $this->expensesRepository->createGasto($data);
    }

    public function editarGasto(array $data): Expense
    {
        return $this->expensesRepository->edit($data);
    }

    public function consultById(string $id): ?Expense
    {
        return $this->expensesRepository->consultById($id);
    }

    public function consultAll(): Collection
    {
        return $this->expensesRepository->consultAll();
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

    public function changeStatus(int $id, string $status): Expense
    {
        return $this->expensesRepository->changeStatus($id, $status);
    }

    public function exportExcel(array $filtros): ExpenseExport
    {
        $build = $this->expensesRepository->buildFilter($filtros);
        return new ExpenseExport($build);
    }

    public function cargarFactura(array $data): Expense
    {
        // Fecha de carga
        $timeZone = new DateTimeZone(env("APP_TIMEZONE"));
        $hoy = new DateTime("now", $timeZone);
        $data["date_upload"] = $hoy->format("Y-m-d");

        // Validaciones mínimas de entrada
        /** @var UploadedFile $file */
        $file = $data["file_invoice"] ?? null;
        $id = $data["id"] ?? null;

        if (!$file instanceof UploadedFile || !$id) {
            throw new \InvalidArgumentException("Datos inválidos para cargar la factura.");
        }


        $relativeDir = $this->ensureInvoiceDirectories((string)$id);
        $meta = $this->storeInvoiceAndGetMeta($file, $relativeDir);


        $data["file_name"] = $meta["file_name"]; // solo UUID sin extensión
        $data["extension_file"] = $meta["extension_file"];
        $data["url_file"] = $meta["url_file"];

        return $this->expensesRepository->cargarFactura($data);
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
        $url = $disk->url($relativeDir . '/' . $filename);

        return [
            'file_name' => $uuid,
            'extension_file' => $extension,
            'url_file' => $url,
        ];
    }
}
