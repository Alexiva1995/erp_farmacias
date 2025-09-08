<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AssistantReportProductExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $query;

    public function __construct($query)
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
            'ID',
            'Producto',
            'Laboratorio',
            'Costo Min',
            'Costo Max',
            'Costo',
            'Ventas',
            'Stock',
            'Promedio Ventas',
            'Análisis'
        ];
    }

    public function map($product): array
    {
        return [
            $product->id, // Esto se reemplazará con el contador en el controlador
            $product->id,
            $product->name,
            $product->laboratory->name ?? 'N/A',
            $product->cost_min ? number_format($product->cost_min, 2) : '0.00',
            $product->cost_max ? number_format($product->cost_max, 2) : '0.00',
            $product->unit_cost ? number_format($product->unit_cost, 2) : '0.00',
            $product->total_sold_completed ?? 0,
            $product->lote_quantity ?? 0,
            $product->promedio_calculado ? number_format($product->promedio_calculado, 2) : '0.00',
            $product->solicitar ? number_format($product->solicitar, 2) : '0.00'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Estilo para el encabezado
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF']
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'color' => ['rgb' => '2979FF'] // Azul corporativo
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                ]
            ],
            // Alineación para columnas numéricas
            'A:B' => [
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                ]
            ],
            'E:K' => [
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT,
                ]
            ],
            // Columnas de texto
            'C:D' => [
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
                ]
            ],
            // Ajustar texto en todas las celdas
            'A:K' => [
                'alignment' => [
                    'wrapText' => true,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP
                ]
            ]
        ];
    }
}
