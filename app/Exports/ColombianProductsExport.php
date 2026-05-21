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
    protected Collection $rows;

    public function __construct(Collection $products)
    {
        // Filtrar solo los que necesitan pedido y ordenar por laboratorio luego por nombre
        $this->rows = $products
            ->filter(fn($p) => (float)($p->solicitar ?? 0) > 0)
            ->sortBy([
                fn($a, $b) => strcmp($a->laboratory->name ?? '', $b->laboratory->name ?? ''),
                fn($a, $b) => strcmp($a->name ?? '', $b->name ?? ''),
            ])
            ->values();
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
            'Producto',
            'Laboratorio',
            'Cantidad a Solicitar',
        ];
    }

    public function map($product): array
    {
        static $index = 0;
        $index++;

        $solicitar = (int) ceil(max(0, (float)($product->solicitar ?? 0)));

        return [
            $index,
            $product->name ?? 'N/A',
            $product->laboratory->name ?? 'Sin Laboratorio',
            $solicitar,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
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
            // Producto y Laboratorio alineados a la izquierda
            'B:C' => [
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
            ],
            // Cantidad centrada y en negrita
            'D:D' => [
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'font'      => ['bold' => true],
            ],
            // Alineación vertical para todos los datos
            "A2:D{$lastRow}" => [
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ],
        ];
    }
}
