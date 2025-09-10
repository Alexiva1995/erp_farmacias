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
    private bool $hasRun = false;
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
        private readonly ?string $activeIngredientCol,
        private readonly ?string $expirationCol,
    ) {
    }

    public function startRow(): int
    {
        return $this->startRow;
    }

    public function collection(Collection $rows)
    {
        if ($this->hasRun) {
            return;
        }

        $this->hasRun = true;
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

        $toNumber = function (string $value): ?float {
            // a) drop everything except digits, comma, dot, Bs.F., $
            $clean = preg_replace('/[^\d,.\$]|(?<!B)s\.F\.(?! )/i', '', $value);

            // b) if any letter is left → garbage
            if (preg_match('/[a-z]/i', $clean)) {
                return null;
            }

            // c) only one decimal separator allowed → turn comma into dot
            $clean = str_replace(',', '.', $clean);

            // d) cast and validate
            $float = (float) $clean;
            return is_finite($float) ? $float : null;
        };

        $rows = $rawRows
            ->takeWhile(fn($row) => !empty(array_filter($row->toArray())))
            ->map(function ($row, $index) use ($rows, $now, $usdCurrency, $toNumber) {
                $cod = trim((string) ($row[$this->colIndex($this->codSupplierCol)] ?? ""));
                $name = trim((string) ($row[$this->colIndex($this->nameCol)] ?? ""));
                $active_ingredient = trim((string) ($row[$this->colIndex($this->activeIngredientCol)] ?? ""));
                $bar = trim((string) ($row[$this->colIndex($this->barcodeCol)] ?? ""));
                $bs = $toNumber($row[$this->colIndex($this->costBsCol)] ?? null);
                $usd = $toNumber($this->costUsdCol === "null"
                    ? $this->castToFloat($bs) / $usdCurrency->rate
                    : $this->castToFloat($row[$this->colIndex($this->costUsdCol)] ?? null));
                $expiration = $row[$this->colIndex($this->expirationCol)] ?? null;

                if ($cod === "") {
                    $cod = null;
                }

                if ($name === "") {
                    $name = null;
                }

                if ($bar === "") {
                    $bar = null;
                }

                if ($bs === null) {
                    $bs = 0;
                }

                if ($expiration != null) {
                    if (!\Datetime::createFromFormat("Y-m-d", $expiration)) {
                        $expiration = \DateTime::createFromFormat("d/m/Y", $expiration)?->format("Y-m-d");
                    }
                }

                $data = [
                    "cod_supplier" => $cod,
                    "name" => $this->cleanCell($name),
                    "barcode_match" => $bar,
                    "quantity" => $row[$this->colIndex($this->qtyCol)] ?? null,
                    "unit_cost" => $this->castToFloat($bs),
                    "unit_cost_usd" => $this->castToFloat($usd),
                    "expiration" => $expiration,
                    "supplier_id" => $this->supplierId,
                    "created_at" => $now,
                    "updated_at" => $now,
                    "connection_date" => $now,
                    'active_ingredient' => $this->cleanCell($active_ingredient),
                    "laboratory" => null,
                    "product_id" => null,
                    "unit_cost_with_discount" => null,
                    "unit_cost_usd_with_discount" => null
                ];

                return $data;
            })
            ->values();

        $products = Product::with("laboratory")
            ->whereIn("barcode", $rows->pluck("barcode")->unique())
            ->get()
            ->keyBy("barcode");

        $this->cleanedRows = $rows->map(function ($row) use ($products) {
            $product = $products->get($row["barcode"] ?? $row["barcode_match"]);
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
