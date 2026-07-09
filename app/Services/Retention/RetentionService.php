<?php

declare(strict_types=1);

namespace App\Services\Retention;

use App\Contracts\Retention as RetentionContract;
use App\Models\Invoice;
use App\Models\Retention;
use App\Repositories\RetentionRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class RetentionService implements RetentionContract
{
    public function __construct(
        protected RetentionRepository $retentionRepository
    ) {}

    public function getInvoicesWithTax($filters = [], $perPage = 10): LengthAwarePaginator
    {
        return $this->retentionRepository->getInvoicesWithTax($filters, $perPage);
    }

    public function generateRetentions(array $invoiceIds): Retention
    {
        return $this->retentionRepository->generateRetentions($invoiceIds);
    }

    public function getGeneratedRetentions($filters = [], $perPage = 10): LengthAwarePaginator
    {
        return $this->retentionRepository->getGeneratedRetentions($filters, $perPage);
    }

    public function prepareRetentionData($source): array
    {
        // La preparación de datos para el PDF puede permanecer en el servicio
        // ya que es lógica de presentación/formato, no de persistencia pura.
        
        if ($source instanceof Retention) {
            $retention = $source->load(['supplier', 'invoices']);
            $invoices = $retention->invoices;
            $supplier = $retention->supplier;
            $comprobanteNumber = $retention->number;
            $dateNow = $retention->date->format('d/m/Y');
            $period = $retention->date->format('Ym');
        } else {
            if (is_array($source)) {
                $invoices = Invoice::with('supplier')->whereIn('id', $source)->get();
            } else {
                $invoices = collect([$source]);
            }

            if ($invoices->isEmpty()) throw new \Exception("Sin facturas.");
            
            $firstInvoice = $invoices->first();
            $supplier = $firstInvoice->supplier;
            $comprobanteNumber = $invoices->pluck('id')->implode('-');
            $dateNow = now()->format('d/m/Y');
            $period = optional($firstInvoice->created_invoice_date)->format('Ym') ?? now()->format('Ym');
        }

        $retentionPercentage = 0.75;
        $totalInvoiceAmount = 0;
        $totalExempt = 0;
        $totalTaxable = 0;
        $totalTax = 0;
        $totalWithheld = 0;

        $invoiceLines = $invoices->map(function ($inv) use ($retentionPercentage, &$totalInvoiceAmount, &$totalExempt, &$totalTaxable, &$totalTax, &$totalWithheld) {
            $taxWithheld = round(($inv->tax_amount ?? 0) * $retentionPercentage, 2);
            $totalInvoiceAmount += (float)$inv->total_amount;
            $totalExempt += (float)$inv->exempt_amount;
            $totalTaxable += (float)$inv->taxable_base;
            $totalTax += (float)$inv->tax_amount;
            $totalWithheld += $taxWithheld;

            return [
                'date' => optional($inv->created_invoice_date)->format('d/m/Y'),
                'number' => $inv->invoice_number,
                'control' => $inv->control_number,
                'total' => (float)$inv->total_amount,
                'exempt_amount' => (float)$inv->exempt_amount,
                'taxable_base' => (float)$inv->taxable_base,
                'tax_amount' => (float)$inv->tax_amount,
                'tax_withheld' => $taxWithheld,
            ];
        });

        return [
            'date_now' => $dateNow,
            'comprobante' => [
                'number' => $comprobanteNumber,
                'period' => $period,
            ],
            'company' => [
                'name' => 'FARMACIA BARRIO SUCRE 2024, C.A.',
                'rif' => 'J505406957',
                'address' => 'CALLE PRINCIPAL LOCAL 05 (L3) SECTOR BARRIO SUCRE LA FRIA TACHIRA',
            ],
            'supplier' => [
                'name' => $supplier->social_reason ?? $supplier->name ?? 'Proveedor Desconocido',
                'rif' => $supplier->rif ?? 'J-00000000-0',
                'address' => $supplier->address ?? 'N/A',
            ],
            'invoices' => $invoiceLines,
            'totals' => [
                'total_amount' => $totalInvoiceAmount,
                'exempt_amount' => $totalExempt,
                'taxable_base' => $totalTaxable,
                'tax_amount' => $totalTax,
                'tax_withheld' => $totalWithheld,
                'retention_percentage' => ($retentionPercentage * 100) . '%',
            ]
        ];
    }

    public function generateAllPendingInRange(string $startDate, string $endDate): int
    {
        return $this->retentionRepository->generateAllPendingInRange($startDate, $endDate);
    }

    public function deleteRetention(int $id): bool
    {
        return $this->retentionRepository->deleteRetention($id);
    }

    public function updateRetention(int $id, array $data): Retention
    {
        return $this->retentionRepository->updateRetention($id, $data);
    }
}
