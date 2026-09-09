<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InventorySnapshotExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithTitle
{
    public function __construct(
        protected Collection $data,
        protected string $reportTitle = 'Foto Finish de Inventario'
    ) {
    }

    public function collection(): Collection
    {
        return $this->data;
    }

    public function headings(): array
    {
        return [
            'ID Producto',
            'Producto / SKU',
            'Laboratorio / Marca',
            'Clasificación Ventas',
            'Ventas Unidades (30d)',
            'Ventas Totales USD (30d)',
            'Stock Actual (Und)',
            'Costo Unitario USD ($)',
            'Precio Venta USD ($)',
            'Valor Inventario USD ($)',
            'Margen (%)',
            'Cobertura (Días)',
            'GMROI Anual (%)',
            'Días para Vencer',
            'Es Sobrestock (>90d)',
        ];
    }

    public function map($item): array
    {
        $coverage = (float) ($item->coverage_days ?? 0);

        return [
            $item->product_id ?? 'N/A',
            $item->product_name ?? 'N/A',
            $item->laboratory_name ?? 'Sin Laboratorio',
            $item->sales_class ?? 'C',
            round((float) ($item->sold_units_30d ?? 0), 2),
            round((float) ($item->total_sales_usd_30d ?? 0), 2),
            (int) round((float) ($item->current_stock_units ?? 0)),
            round((float) ($item->unit_cost_usd ?? 0), 4),
            round((float) ($item->sale_price_usd ?? 0), 4),
            round((float) ($item->inventory_value_usd ?? 0), 2),
            round((float) ($item->margin_percentage ?? 0), 2) . '%',
            $coverage >= 999 ? 'Sin Ventas (999d)' : round($coverage, 1),
            round((float) ($item->gmroi_annual_percentage ?? 0), 2) . '%',
            $item->days_to_expiration !== null ? (int) $item->days_to_expiration : 'Sin fecha',
            $item->is_overstock ? 'SÍ (Sobrestock)' : 'NO',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();

        // Estilo de la fila de encabezados
        $sheet->getStyle("A1:{$highestColumn}1")->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E293B'], // Slate Dark
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Bordes suaves
        $sheet->getStyle("A1:{$highestColumn}{$highestRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CBD5E1'],
                ],
            ],
        ]);

        return [];
    }

    public function title(): string
    {
        return mb_substr($this->reportTitle, 0, 31);
    }
}
