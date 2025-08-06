<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Database\Eloquent\Builder;

class StockProductExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
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
            '#',
            'Producto',
            'Ventas',
            'Stock',
            'Promedio',
            'Preferencia',
            'Diferencia'
        ];
    }

    public function map($product): array
    {
        return [
            $product->id,
            $product->name ?? 'N/A',
            $product->total_sold_completed ?? 0,
            $product->stock ?? 0,
            $product->promedio_calculado ?? 0,
            $product->preferencia_product ?? 'N/A',
            $product->diferencia_product ?? 'N/A'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Estilo para el encabezado (fila 1)
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF']
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'color' => ['rgb' => '2979FF'] // Azul corporativo
                ]
            ],
            // Ajustar texto en todas las columnas (A-G)
            'A:G' => [
                'alignment' => [
                    'wrapText' => true,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP
                ]
            ],
            // Formato numérico para columnas de ventas, stock y promedio
            'C:E' => [
                'numberFormat' => [
                    'formatCode' => '#,##0'
                ]
            ]
        ];
    }
}
