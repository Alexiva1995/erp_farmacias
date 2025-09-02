<?php

namespace App\Exports;

use App\Models\ExchangeRate;
use App\Models\Product;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Illuminate\Support\Facades\Artisan;

class SupplierImport implements ToCollection, WithStartRow, WithCalculatedFormulas
{
    private Collection $cleanedRows;

    public function __construct(
        private readonly int $supplierId,
        private readonly int $startRow,
        private readonly string $codSupplierCol,
        private readonly string $nameCol,
        private readonly string $barcodeCol,
        private readonly ?string $qtyCol,
        private readonly string $costBsCol,
        private readonly ?string $costUsdCol,
        private readonly ?string $expirationCol,
    ) {}

    public function startRow(): int
    {
        return $this->startRow;
    }

    public function collection(Collection $rows)
    {
        $this->cleanedRows = collect();

        if (
            $this->supplierId === "null" ||
            $this->startRow === "null" ||
            $this->codSupplierCol === "null" ||
            $this->nameCol === "null" ||
            $this->barcodeCol === "null" ||
            $this->costBsCol === "null"
        ) {
            throw new \Exception("Los campos no se encuentran definidos");
        }

        $now = now();

        $barcodes = $rows
            ->pluck($this->colIndex($this->barcodeCol))
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $products = Product::with("laboratory")->whereIn("barcode", $barcodes)->get()->keyBy("barcode");
        $usdCurrency = ExchangeRate::where("currency_code", "USD")
            ->whereDate("created_at", \Carbon\Carbon::today())
            ->first();

        if (!isset($usdCurrency)) {
            $exitCode = Artisan::call("app:update-exchange-rate");

            if ($exitCode === 0) {
                $usdCurrency = ExchangeRate::where("currency_code", "USD")
                    ->whereDate("created_at", \Carbon\Carbon::today())
                    ->first();
            } else {
                \Log::error("Failed to fetch exchange rate");
                throw new \Exception("No se pudo guardar la tasa del día USD");
            }
        }

        $rawRows = $rows;

        $rows = $rawRows
            ->map(function ($row) use ($rows, $now, $usdCurrency) {
                $cod = trim((string) ($row[$this->colIndex($this->codSupplierCol)] ?? ""));
                $name = trim((string) ($row[$this->colIndex($this->nameCol)] ?? ""));
                $bar = trim((string) ($row[$this->colIndex($this->barcodeCol)] ?? ""));
                $bs = $row[$this->colIndex($this->costBsCol)] ?? null;
                $rowNum = $rows->search($row) + $this->startRow;

                if ($cod === "") {
                    throw new \Exception(
                        "No se pudo encontrar el código del producto en la fila {$rowNum}, verifique que la columna ({$this->codSupplierCol}) sea correcta.",
                    );
                }

                if ($name === "") {
                    throw new \Exception(
                        "No se pudo encontrar el nombre del producto en la fila {$rowNum}, verifique que la columna ({$this->nameCol}) sea correcta.",
                    );
                }

                if ($bar === "") {
                    throw new \Exception(
                        "No se pudo encontrar el código de barras en la fila {$rowNum}, verifique que la columna ({$this->barcodeCol}) sea correcta.",
                    );
                }

                if ($bs === null) {
                    throw new \Exception(
                        "No se pudo encontrar el coste unitario (bs) en la fila {$rowNum}, verifique que la columna ({$this->costBsCol}) sea correcta.",
                    );
                }

                return [
                    "cod_supplier" => $cod,
                    "name" => $this->cleanCell($name),
                    "barcode" => $bar,
                    "quantity" => $row[$this->colIndex($this->qtyCol)] ?? null,
                    "unit_cost" => $this->castToFloat($bs),
                    "unit_cost_usd" =>
                        $this->costUsdCol === "null"
                            ? $this->castToFloat($bs) / $usdCurrency->rate
                            : $this->castToFloat($row[$this->colIndex($this->costUsdCol)] ?? null),
                    "expiration" => $row[$this->colIndex($this->expirationCol)] ?? null,
                    "supplier_id" => $this->supplierId,
                    "created_at" => $now,
                    "updated_at" => $now,
                    "connection_date" => $now,
                    "laboratory" => null,
                    "product_id" => null,
                    "unit_cost_with_discount" => null,
                    "unit_cost_usd_with_discount" => null,
                ];
            })
            ->filter();

        $products = Product::with("laboratory")
            ->whereIn("barcode", $rows->pluck("barcode")->unique())
            ->get()
            ->keyBy("barcode");

        $this->cleanedRows = $rows->map(function ($row) use ($products) {
            $product = $products->get($row["barcode"]);
            return array_merge($row, [
                "laboratory" => $product?->laboratory?->name,
                "product_id" => $product?->id,
            ]);
        });
    }

    public function getRows(): Collection
    {
        return $this->cleanedRows;
    }

    private function colIndex(?string $letters): ?int
    {
        if (!$letters) {
            return null;
        }
        $letters = strtoupper($letters);
        $len = strlen($letters);
        $index = 0;
        for ($i = 0; $i < $len; $i++) {
            $index = $index * 26 + (ord($letters[$i]) - 64);
        }
        return $index - 1;
    }

    private function cleanCell(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = mb_convert_encoding($value, "UTF-8", "UTF-8");

        $value = preg_replace("/\p{Z}+/u", " ", $value);

        $value = preg_replace("/\s+/u", " ", $value);

        return trim($value);
    }

    private function castToFloat(?float $value)
    {
        if (is_null($value)) {
            return 0;
        }
        return number_format((float) $value, 2, ".", "");
    }
}
