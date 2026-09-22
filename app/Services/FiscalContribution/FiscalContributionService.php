<?php

declare(strict_types=1);

namespace App\Services\FiscalContribution;

use App\Contracts\FiscalContributionContract;
use App\Models\FiscalContribution;
use Illuminate\Pagination\LengthAwarePaginator;

class FiscalContributionService
{
    public function __construct(
        protected FiscalContributionContract $repository
    ) {}

    public function getPaginated(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->repository->getPaginated($filters, $perPage);
    }

    public function getKpis(array $filters): array
    {
        return $this->repository->getKpis($filters);
    }

    public function create(array $data, ?int $userId = null): FiscalContribution
    {
        $data['created_by'] = $userId;
        return $this->repository->create($data);
    }

    public function update(int $id, array $data): FiscalContribution
    {
        return $this->repository->update($id, $data);
    }

    public function delete(int $id): bool
    {
        return $this->repository->delete($id);
    }

    public function batchImport(array $items, string $source = 'smart_paste', ?int $userId = null): array
    {
        return $this->repository->batchUpsert($items, $source, $userId);
    }

    public function togglePaymentStatus(int $id, array $paymentData): FiscalContribution
    {
        return $this->repository->togglePaymentStatus($id, $paymentData);
    }

    /**
     * Parseador del texto crudo copiado directamente de la tabla del portal SENIAT.
     */
    public function parseSeniatRawText(string $rawText): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($rawText));
        $parsed = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Ignorar cabeceras típicas
            if (preg_match('/Periodo|Impuesto|Documento|Fecha de Operaci/i', $line)) {
                continue;
            }

            // Dividir por tabulaciones o múltiples espacios
            $parts = preg_split('/\t+|\s{2,}/', $line);

            if (count($parts) >= 6) {
                $period = trim($parts[0]);
                $taxType = trim($parts[1]);
                $document = trim($parts[2]);
                $opDate = $this->formatDateToIso(trim($parts[3]));
                $dueDate = $this->formatDateToIso(trim($parts[4]));
                $amount = $this->parseAmount(trim($parts[5]));

                if ($period && $taxType && $document && $opDate && $dueDate) {
                    $parsed[] = [
                        'period'          => $period,
                        'tax_type'        => $taxType,
                        'document_number' => $document,
                        'operation_date'  => $opDate,
                        'due_date'        => $dueDate,
                        'amount'          => $amount,
                        'status'          => 'pending',
                    ];
                }
            }
        }

        return $parsed;
    }

    private function formatDateToIso(string $dateStr): ?string
    {
        $clean = trim($dateStr);
        $parts = explode('/', $clean);
        if (count($parts) === 3) {
            return sprintf('%04d-%02d-%02d', (int)$parts[2], (int)$parts[1], (int)$parts[0]);
        }
        return null;
    }

    private function parseAmount(string $amountStr): float
    {
        $clean = preg_replace('/[^\d.,]/', '', $amountStr);
        // Formato venezolano: 11.639,05 -> 11639.05
        $clean = str_replace('.', '', $clean);
        $clean = str_replace(',', '.', $clean);
        return (float)$clean;
    }
}
