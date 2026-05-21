<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class ColombianProductsExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithTitle
{
    /**
     * Colección de productos ya procesados (con solicitar calculado).
     * Agrupados por laboratorio para el reporte.
     */
    protected Collection $rows;

    public function __construct(Collection $products)
    {
        // Agrupar por laboratorio y sumar la cantidad a solicitar por laboratorio
        $grouped = $products
            ->filter(fn($p) => (float)($p->solicitar ?? 0) > 0) // Solo los que necesitan pedido
            ->groupBy(fn($p) => $p->laboratory->name ?? 'Sin Laboratorio')
            ->map(fn($items, $labName) => [
                'laboratorio'    => $labName,
                'total_solicitar' => $items->sum(fn($p) => (int) ceil(max(0, (float)($p->solicitar ?? 0)))),
                'productos'      => $items->count(),
            ])
            ->sortBy('laboratorio')
            ->values();

        $this->rows = $grouped;
    }

    public function title(): string
    {
        return 'Colombia - Pedido';
    }

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            '#',
            'Laboratorio',
            'Cantidad a Solicitar',
            'N° Productos',
        ];
    }

    public function map($row): array
    {
        static $index = 0;
        $index++;

        return [
            $index,
            $row['laboratorio'],
            $row['total_solicitar'],
            $row['productos'],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        // Fila de totales al final (después de los datos)
        $lastRow = $this->rows->count() + 1;

        return [
            // Encabezado: fondo verde Colombia, texto blanco
            1 => [
                'font' => [
                    'bold'  => true,
                    'size'  => 12,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'color'    => ['rgb' => '2E7D32'], // Verde Colombia
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical'   => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'bottom' => [
                        'borderStyle' => Border::BORDER_MEDIUM,
                        'color'       => ['rgb' => '1B5E20'],
                    ],
                ],
            ],

            // Columna # centrada
            'A:A' => [
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],

            // Columna laboratorio
            'B:B' => [
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
            ],

            // Columnas numéricas alineadas a la derecha
            'C:D' => [
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'font'      => ['bold' => true],
            ],

            // Filas alternas: resaltar filas impares
            "A2:D{$lastRow}" => [
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ],
        ];
    }
}
