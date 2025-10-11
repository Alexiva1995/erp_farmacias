<?php

namespace App\Exports;

use App\Models\Product;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithStartRow;

class SupplierImport implements ToCollection, WithStartRow, WithCalculatedFormulas
{
    private bool $hasRun = false;
    private Collection $cleanedRows;

    public function __construct(
        private readonly int $supplierId,
        private readonly int $startRow,
        private readonly string $codSupplierCol,
        private readonly string $nameCol,
        private readonly ?string $barcodeCol,
        private readonly ?string $qtyCol,
        private readonly ?float $currencyCol,
        private readonly ?string $costBsCol,
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
            $this->nameCol === "null"
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

        $rawRows = $rows;
        $currency = $this->currencyCol ?? 1;

        $toNumber = function (string $value): ?float {
            $clean = preg_replace('/[^\d,.\$]|(?<!B)s\.F\.(?! )/i', '', $value);

            if (preg_match('/[a-z]/i', $clean)) {
                return null;
            }

            $clean = str_replace(',', '.', $clean);

            $float = (float) $clean;
            return is_finite($float) ? $float : null;
        };

        $rows = $rawRows
            ->takeWhile(fn($row) => !empty(array_filter($row->toArray())))
            ->map(function ($row) use ($now, $currency, $toNumber) {
                // Skip processing if $row is null or not an array/object
                if (is_null($row) || (is_array($row) && empty($row)) || (is_object($row) && empty((array) $row))) {
                    return null;
                }

                $cod = trim((string) ($row[$this->colIndex($this->codSupplierCol)] ?? ""));
                $name = trim((string) ($row[$this->colIndex($this->nameCol)] ?? ""));
                $active_ingredient = trim((string) ($row[$this->colIndex($this->activeIngredientCol)] ?? ""));
                $bar = trim((string) ($row[$this->colIndex($this->barcodeCol)] ?? ""));
                $bs = $this->costBsCol === null
                    ? null
                    : $toNumber($row[$this->colIndex($this->costBsCol)] ?? null);
                $usd = $toNumber($this->costUsdCol === "null"
                    ? $this->castToFloat($bs) / $currency
                    : $this->castToFloat($row[$this->colIndex($this->costUsdCol)] ?? null));

                if ($bs == null && $usd != null) {
                    $bs = $usd * $currency;
                } elseif ($usd == null && $bs != null) {
                    $usd = round($bs / $currency, 2);
                }

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

                if ($name === null) {
                    return null;
                }

                if ($expiration != null) {
                    $date = \DateTime::createFromFormat("d/m/Y", $expiration);
                    $expiration = $date !== false
                        ? $date->format("Y-m-d")
                        : (($date = \DateTime::createFromFormat("Y-m-d", $expiration)) !== false
                            ? $date->format("Y-m-d")
                            : null);
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
            ->filter()
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
