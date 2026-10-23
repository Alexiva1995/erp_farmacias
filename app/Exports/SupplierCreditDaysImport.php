<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class SupplierCreditDaysImport implements ToCollection, WithCalculatedFormulas, WithChunkReading, WithMultipleSheets
{
    /**
     * Mapa de [cod_supplier => credit_days]
     *
     * @var array<string, int>
     */
    private array $creditDaysMap = [];

    private ?int $resolvedCodIdx = null;
    private ?int $resolvedCreditIdx = null;

    public function __construct(
        private readonly ?string $codSupplierCol,
        private readonly string $creditDaysCol,
    ) {
        $this->resolvedCodIdx = $this->colIndex($this->codSupplierCol);
        $this->resolvedCreditIdx = $this->colIndex($this->creditDaysCol);
    }

    public function sheets(): array
    {
        return [
            0 => $this,
        ];
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function collection(Collection $rows): void
    {
        if ($this->resolvedCreditIdx === null) {
            return;
        }

        foreach ($rows as $row) {
            $rowArr = $row instanceof Collection ? $row->toArray() : (array) $row;

            // Si no se definió explícitamente la columna del código de proveedor, intentar autodetectarla en encabezados
            if ($this->resolvedCodIdx === null) {
                foreach ($rowArr as $idx => $cellVal) {
                    $upper = strtoupper(trim((string) ($cellVal ?? '')));
                    if (in_array($upper, ['CODIGO', 'CÓDIGO', 'COD', 'COD_ARTICULO', 'COD. ARTICULO', 'CODIGO PROVEEDOR', 'ITEM', 'REF', 'REFERENCIA'], true)) {
                        $this->resolvedCodIdx = (int) $idx;
                        break;
                    }
                }
            }

            $codIdx = $this->resolvedCodIdx ?? 0;
            $rawCode = trim((string) ($rowArr[$codIdx] ?? ''));
            $rawCredit = $rowArr[$this->resolvedCreditIdx] ?? null;

            if ($rawCode === '' || in_array(strtoupper($rawCode), ['CODIGO', 'CÓDIGO', 'COD', 'LISTADO GENERAL', 'DESCRIPCION', 'DESCRIPCIÓN'], true)) {
                continue;
            }

            $creditDays = SupplierImport::parseCreditDays($rawCredit);
            if ($creditDays === null) {
                continue;
            }

            // Guardar código exacto y código normalizado sin ceros a la izquierda
            $cleanCode = strtoupper($rawCode);
            $this->creditDaysMap[$cleanCode] = $creditDays;

            $noZeroes = ltrim($cleanCode, '0');
            if ($noZeroes !== '') {
                $this->creditDaysMap[$noZeroes] = $creditDays;
            }
        }
    }

    /**
     * @return array<string, int>
     */
    public function getCreditDaysMap(): array
    {
        return $this->creditDaysMap;
    }

    private function colIndex(?string $letters): ?int
    {
        if ($letters === null || trim($letters) === '') {
            return null;
        }
        $letters = trim($letters);
        if (is_numeric($letters)) {
            $num = (int) $letters;
            return $num > 0 ? $num - 1 : 0;
        }
        $letters = strtoupper($letters);
        if (!preg_match('/^[A-Z]+$/', $letters)) {
            return null;
        }
        $len = strlen($letters);
        $index = 0;
        for ($i = 0; $i < $len; $i++) {
            $index = $index * 26 + (ord($letters[$i]) - 64);
        }
        return $index - 1;
    }
}
