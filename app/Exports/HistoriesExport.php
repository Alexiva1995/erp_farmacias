<?php

namespace App\Exports;

use App\Models\FiscalHistory;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Database\Eloquent\Builder;

class HistoriesExport implements FromQuery, WithHeadings, WithMapping
{
    protected $query;

    public function __construct(Builder $query)
    {
        // Recibimos la consulta ya filtrada desde el controlador
        $this->query = $query;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query()
    {
        return $this->query;
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'Fecha',
            '#',
            'Razón Social',
            'ID',
            'Dirección',
            'Exento',
            'IVA',
            'Total',
        ];
    }

    /**
     * @param FiscalHistory $history
     * @return array
     */
    public function map($history): array
    {
        return [
            $history->invoice_date,
            $history->invoice_number,
            $history->business_name,
            $history->id,
            $history->address ?? 'N/A',
            $history->exempt_amount ?? 'N/A',
            $history->iva_amount,
            $history->total_amount,
        ];
    }
}
