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

class AbcReportExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithTitle
{
    /**
     * @param Collection $data Colección de productos calculados del reporte ABC
     * @param string $reportTitle Título o tipo del reporte
     */
    public function __construct(
        protected Collection $data,
        protected string $reportTitle = 'Reporte ABC Multicriterio'
    ) {
    }

    /**
     * @return Collection
     */
    public function collection(): Collection
    {
        return $this->data;
    }

    /**
     * Encabezados de la hoja Excel.
     *
     * @return array
     */
    public function headings(): array
    {
        return [
            'ID',
            'Producto',
            'Laboratorio',
            'Unidades Vendidas',
            'Ventas Totales ($)',
            'Costo Total ($)',
            'Margen ($)',
            'Margen (%)',
            'GMROI Anual (%)',
            'Stock Actual',
            'Costo Unitario ($)',
            'Valor Inventario ($)',
            'Cobertura (Días)',
            'Clasif. Ventas',
            'Clasif. Margen',
            'Rotación (XYZ)',
            'Perfil ABC-XYZ',
            'Estado Stock',
            'Próximo Vencimiento',
            'Días Restantes',
            'Riesgo FEFO',
        ];
    }

    /**
     * Mapeo de cada registro a las columnas del Excel.
     *
     * @param object $item
     * @return array
     */
    public function map($item): array
    {
        $currentStock = (float) ($item->current_stock ?? 0);
        $inventoryDays = (float) ($item->inventory_days ?? 0);
        $classSales = $item->class_sales ?? 'C';

        // Determinación del estado de stock
        $stockStatus = 'Normal';
        if ($currentStock <= 0) {
            $stockStatus = ($classSales === 'A' || $classSales === 'B') ? 'QUIEBRE CRÍTICO' : 'Agotado';
        } elseif ($inventoryDays > 0 && $inventoryDays < 10 && $classSales === 'A') {
            $stockStatus = 'Riesgo de Quiebre (<10d)';
        } elseif (($item->sold_units ?? 0) <= 0 && $currentStock > 0) {
            $stockStatus = 'Stock Muerto';
        }

        $fefoRisk = !empty($item->has_expiration_risk) ? 'ALTO RIESGO' : (!empty($item->is_expiring_soon) ? 'Por Vencer (<=180d)' : 'Normal');

        return [
            $item->id ?? 'N/A',
            $item->product_name ?? $item->name ?? 'N/A',
            $item->laboratory_name ?? 'Sin Laboratorio',
            (int) ($item->sold_units ?? 0),
            round((float) ($item->total_sales ?? 0), 2),
            round((float) ($item->total_cost ?? 0), 2),
            round((float) ($item->margin_amount ?? 0), 2),
            round((float) ($item->margin_percentage ?? 0), 2) . '%',
            round((float) ($item->gmroi ?? 0), 2) . '%',
            (float) ($item->current_stock ?? 0),
            round((float) ($item->last_cost ?? 0), 2),
            round((float) ($item->inventory_value ?? 0), 2),
            $inventoryDays >= 9999 ? 'Sin Rotación' : round($inventoryDays, 1),
            $item->class_sales ?? 'C',
            $item->class_margin ?? 'C',
            $item->class_rotation ?? 'Z',
            $item->final_classification ?? 'N/A',
            $stockStatus,
            $item->next_expiration_date ?? 'N/D',
            isset($item->days_to_expiration) ? (int) $item->days_to_expiration : 'N/D',
            $fefoRisk,
        ];
    }

    /**
     * Estilos aplicados a la hoja de cálculo.
     *
     * @param Worksheet $sheet
     * @return array
     */
    public function styles(Worksheet $sheet): array
    {
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();

        // Estilo de la fila de encabezados (Fila 1)
        $sheet->getStyle("A1:{$highestColumn}1")->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E293B'], // Azul pizarra oscuro corporativo
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);

        $sheet->getRowDimension(1)->setRowHeight(28);

        // Alineaciones por columna de datos si hay filas
        if ($highestRow > 1) {
            $sheet->getStyle("A2:A{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("D2:D{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("E2:H{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("I2:I{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("J2:L{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("M2:M{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("N2:Q{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("R2:R{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Bordes delgados para todas las celdas
            $sheet->getStyle("A1:{$highestColumn}{$highestRow}")->getBorders()->getAllBorders()->applyFromArray([
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'E2E8F0'],
            ]);
        }

        return [];
    }

    /**
     * Título de la pestaña en el libro Excel.
     *
     * @return string
     */
    public function title(): string
    {
        return mb_substr($this->reportTitle, 0, 31);
    }
}
