<?php

namespace App\Exports;

use App\Models\InventoryMovement;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class TraceabilityExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(private Builder $query)
    {
    }

    /**
     * @return Builder
     */
    public function query()
    {
        return $this->query;
    }

    /**
     *
     *
     * @return array
     */
    public function headings(): array
    {
        return [
            'ID Venta',
            'Fecha',
            'Cliente',
            'Email Cliente',
            'Método de Pago',
            'Total',
        ];
    }

    /**
     *
     *
     * @param InventoryMovement $sale
     * @return array
     */
    public function map($sale): array
    {
        return [
            $sale->id,
            $sale->movement_date,
            $sale->user->username ?? 'N/A',
            $sale->user->email ?? 'N/A',
            $sale->movement_type,
            $sale->order_id != null ? $sale->order->total_amount : ($sale->invoice_id != null ? $sale->invoice->total_amount : 'N/A'),
        ];
    }
}
