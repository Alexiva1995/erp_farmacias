<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Illuminate\Database\Eloquent\Builder;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExpiringLotsExport implements FromQuery, WithHeadings, WithMapping, WithStyles
{
    protected $query;

    public function __construct(Builder $query)
    {
        $this->query = $query;
    }

    public function query()
    {
        return $this->query;
    }

    public function headings(): array
    {
        return [
            'ID Producto',
            'Producto',
            'Laboratorio',
            'No. Lote',
            'F. Vencimiento',
            'Stock Lote',
        ];
    }

    public function map($lot): array
    {
        return [
            $lot->product->id ?? '—',
            $lot->product->name ?? '—',
            $lot->product->laboratory->name ?? '—',
            $lot->lot_number,
            $lot->expiration_date->format('Y-m-d'),
            $lot->quantity,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
