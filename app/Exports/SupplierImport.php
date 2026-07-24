<?php

namespace App\Exports;

use App\Models\Product;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Illuminate\Support\Facades\Log;

class SupplierImport implements ToCollection, WithStartRow, WithCalculatedFormulas, WithChunkReading, WithBatchInserts
{
    private Collection $cleanedRows;

    public function __construct(
        private readonly int $supplierId,
        private readonly int $startRow,
        private readonly ?string $codSupplierCol,
        private readonly string $nameCol,
        private readonly ?string $barcodeCol,
        private readonly ?string $qtyCol,
        private readonly ?float $currencyCol,
        private readonly ?string $costBsCol,
        private readonly ?string $costUsdCol,
        private readonly ?string $activeIngredientCol,
        private readonly ?string $expirationCol,
    ) {
        $this->cleanedRows = collect();
    }

    public function startRow(): int
    {
        return $this->startRow;
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function batchSize(): int
    {
        return 500;
    }

    public function collection(Collection $rows)
    {
        if (
            $this->supplierId === "null" ||
            $this->startRow === "null" ||
            $this->nameCol === "null"
        ) {
            throw new \Exception("Los campos fila de inicio y nombre no se encuentran definidos");
        }

        $now = now();
        $currency = $this->currencyCol ?? 1;

        $toNumber = function (string|null $value): ?float {
            if ($value === null || $value === '') {
                return null;
            }

            // 1. Quitar todo lo que no sea dígito, coma, punto o signo menos
            $clean = preg_replace('/[^\d,.\-]/', '', (string) $value);

            // 2. Si empieza por puntos o comas (sobras de prefijos como Bs.F.), quitarlos del inicio
            $clean = ltrim($clean, '.,');

            // 3. Identificar cuál es el separador decimal
            $lastComma = strrpos($clean, ',');
            $lastDot = strrpos($clean, '.');

            if ($lastComma !== false && $lastDot !== false) {
                if ($lastComma > $lastDot) {
                    // Coma es decimal (ej: 1.234,56)
                    $clean = str_replace('.', '', $clean);
                    $clean = str_replace(',', '.', $clean);
                } else {
                    // Punto es decimal (ej: 1,234.56)
                    $clean = str_replace(',', '', $clean);
                }
            } elseif ($lastComma !== false) {
                // Solo hay coma. Si hay más de una, es separador de miles.
                if (substr_count($clean, ',') > 1) {
                    $clean = str_replace(',', '', $clean);
                } else {
                    // Una sola coma: asumimos decimal
                    $clean = str_replace(',', '.', $clean);
                }
            } elseif ($lastDot !== false) {
                // Solo hay punto. Si hay más de uno, es separador de miles.
                if (substr_count($clean, '.') > 1) {
                    $clean = str_replace('.', '', $clean);
                }
            }

            $float = (float) $clean;
            return is_finite($float) ? $float : null;
        };

        $barcodes = $rows
            ->map(fn($row) => $row[$this->colIndex($this->barcodeCol)] ?? null)
            ->filter()
            ->map(fn($b) => trim((string) $b))
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $products = Product::with('laboratory')
            ->whereIn('barcode', $barcodes)
            ->get()
            ->keyBy('barcode');

        $processedChunk = $rows
            ->filter(function ($row) {
                $cod = trim((string) ($row[$this->colIndex($this->codSupplierCol)] ?? ""));
                $name = trim((string) ($row[$this->colIndex($this->nameCol)] ?? ""));

                return !empty(array_filter((array) $row))
                    && $name !== ''
                    && $name !== 'DESCRIPCIÓN'
                    && $cod !== 'LISTADO GENERAL'
                    && $cod !== 'CODIGO';
            })
            ->map(function ($row) use ($now, $currency, $toNumber, $products) {
                $cod = trim((string) ($row[$this->colIndex($this->codSupplierCol)] ?? ""));
                $name = trim((string) ($row[$this->colIndex($this->nameCol)] ?? ""));
                $active_ingredient = trim((string) ($row[$this->colIndex($this->activeIngredientCol)] ?? ""));
                $bar = trim((string) ($row[$this->colIndex($this->barcodeCol)] ?? ""));

                $bsRaw = $this->costBsCol !== null
                    ? $row[$this->colIndex($this->costBsCol)] ?? null
                    : null;
                $bs = $bsRaw !== null ? $toNumber((string) $bsRaw) : null;

                $usdRaw = $this->costUsdCol !== null
                    ? $row[$this->colIndex($this->costUsdCol)] ?? null
                    : null;
                $usd = $usdRaw !== null ? $toNumber((string) $usdRaw) : null;

                if ($bs === null && $usd !== null) {
                    $bs = $usd * $currency;
                } elseif ($usd === null && $bs !== null) {
                    $usd = round($bs / $currency, 2);
                }

                $cod = $cod === "" ? null : $cod;
                $name = $name === "" ? null : $name;
                $bar = $bar === "" ? null : $bar;
                $bs = $bs ?? 0.0;
                $usd = $usd ?? 0.0;

                if ($name === null) {
                    return null;
                }

                $expiration = $row[$this->colIndex($this->expirationCol)] ?? null;
                if ($expiration) {
                    $date = \DateTime::createFromFormat('d/m/Y', $expiration)
                        ?: \DateTime::createFromFormat('Y-m-d', $expiration);
                    $expiration = $date ? $date->format('Y-m-d') : null;
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
                    "unit_cost_usd_with_discount" => null,
                ];

                $product = $products->get($bar);
                if ($product) {
                    $data['laboratory'] = $product->laboratory?->name;
                    $data['product_id'] = $product->id;
                }

                return $data;
            })
            ->filter();

        $this->cleanedRows = $this->cleanedRows->concat($processedChunk);
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
        $letters = trim($letters);
        if (is_numeric($letters)) {
            return (int) $letters - 1;
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

    private function castToFloat(?float $value): string
    {
        if (is_null($value)) {
            return "0.00";
        }
        return number_format((float) $value, 2, ".", "");
    }
}
