<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\GeneralSetting;
use App\Models\InventoryBook;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\SheetView;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InventoryBookExport implements FromCollection, WithHeadings, WithMapping, WithColumnFormatting, WithStyles, WithEvents
{
    protected GeneralSetting $settings;
    protected int $rowCount = 0;

    public function __construct(
        protected InventoryBook $book,
        ?GeneralSetting $settings = null
    ) {
        $this->settings = $settings ?? GeneralSetting::first() ?? new GeneralSetting();
    }

    public function collection()
    {
        if ($this->book->relationLoaded('entries')) {
            $collection = $this->book->entries;
        } else {
            $collection = $this->book->entries()->orderBy('operation_number', 'asc')->get();
        }
        $this->rowCount = $collection->count();
        return $collection;
    }

    public function headings(): array
    {
        $monthName = mb_strtoupper(Carbon::create($this->book->year, $this->book->month, 1)->locale('es')->monthName, 'UTF-8');
        $taxpayerType = ($this->settings->special_taxpayer_status === 'activada' || $this->settings->enable_ce)
            ? 'CONTRIBUYENTE ESPECIAL'
            : 'CONTRIBUYENTE ORDINARIO';

        $companyName = mb_strtoupper($this->settings->app_name ?: 'FARMACIA', 'UTF-8');
        $companyRif = mb_strtoupper($this->settings->app_rif ?: $this->settings->rif ?: 'J-00000000-0', 'UTF-8');
        $companyAddress = mb_strtoupper($this->settings->address ?: 'DIRECCIÓN PRINCIPAL', 'UTF-8');

        return [
            // Fila 1: Título Principal
            ['Registro detallado de entrada y salida de inventario de mercancia'],
            // Fila 2: Datos generales
            [
                'Mes: ' . $monthName,
                '',
                'Ejercicio Gravable: ' . $this->book->year,
                '',
                'Razón Social: ' . $companyName,
                '',
                '',
                '',
                'Rif. Del Contribuyente: ' . $companyRif,
            ],
            // Fila 3: Dirección
            ['DIRECCIÓN PRINCIPAL: ' . $companyAddress],
            // Fila 4: Fundamento legal Art. 177 y condición fiscal
            [
                'Movimiento de Unidades según el artículo 177 ley de impuesto sobre la renta.',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                $taxpayerType,
            ],
            // Fila 5: Agrupadores superiores
            [
                '', '', '', '',
                'UNIDADES', '', '', '', '', '',
                'BOLIVARES', '', '', '', '', '',
            ],
            // Fila 6: Columnas exactas del SENIAT
            [
                'Nº',
                'CODIGO DEL PRODUCTO',
                'DESCRIPCION DEL PRODUCTO',
                'COSTO PROMEDIO UNITARIO',
                'EXISTENCIA INICIAL',
                'ENTRADA COMPRAS',
                'SALIDAS VENTAS',
                'RETIROS',
                'AUTO CONSUMOS',
                'EXISTENCIA',
                'VALOR ANTERIOR EN BS',
                'COSTOS ENTRADA COMPRAS',
                'COSTOS SALIDAS VENTAS',
                'COSTOS RETIROS',
                'COSTOS AUTO CONSUMOS',
                'VALOR DEL INVENTARIO',
            ],
        ];
    }

    public function map($entry): array
    {
        return [
            $entry->operation_number,
            $entry->product_code,
            $entry->product_description,
            (float) $entry->unit_cost_bs,
            (float) $entry->initial_stock,
            (float) $entry->purchase_entries,
            (float) $entry->sales_exits,
            (float) $entry->withdrawals,
            (float) $entry->self_consumptions,
            (float) $entry->final_stock,
            (float) $entry->previous_value_bs,
            (float) $entry->purchase_entries_cost_bs,
            (float) $entry->sales_exits_cost_bs,
            (float) $entry->withdrawals_cost_bs,
            (float) $entry->self_consumptions_cost_bs,
            (float) $entry->final_inventory_value_bs,
        ];
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_NUMBER,
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
            'D' => '#,##0.00',
            'E' => '#,##0.00',
            'F' => '#,##0.00',
            'G' => '#,##0.00',
            'H' => '#,##0.00',
            'I' => '#,##0.00',
            'J' => '#,##0.00',
            'K' => '#,##0.00',
            'L' => '#,##0.00',
            'M' => '#,##0.00',
            'N' => '#,##0.00',
            'O' => '#,##0.00',
            'P' => '#,##0.00',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Anchos de columna optimizados para impresión horizontal
        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(14);
        $sheet->getColumnDimension('C')->setWidth(35);
        $sheet->getColumnDimension('D')->setWidth(16);
        $sheet->getColumnDimension('E')->setWidth(13);
        $sheet->getColumnDimension('F')->setWidth(13);
        $sheet->getColumnDimension('G')->setWidth(13);
        $sheet->getColumnDimension('H')->setWidth(12);
        $sheet->getColumnDimension('I')->setWidth(13);
        $sheet->getColumnDimension('J')->setWidth(13);
        $sheet->getColumnDimension('K')->setWidth(16);
        $sheet->getColumnDimension('L')->setWidth(16);
        $sheet->getColumnDimension('M')->setWidth(16);
        $sheet->getColumnDimension('N')->setWidth(15);
        $sheet->getColumnDimension('O')->setWidth(16);
        $sheet->getColumnDimension('P')->setWidth(18);

        // Alineación vertical y ajuste de texto
        $sheet->getStyle('A1:P6')->getAlignment()->setWrapText(true);
        $sheet->getStyle('C')->getAlignment()->setWrapText(true);

        return [
            1 => ['font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '1E3A8A']]],
            4 => ['font' => ['bold' => true, 'italic' => true, 'size' => 9, 'color' => ['rgb' => '475569']]],
            5 => ['font' => ['bold' => true, 'size' => 10]],
            6 => ['font' => ['bold' => true, 'size' => 8]],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Combinaciones de cabecera
                $sheet->mergeCells('A1:P1');
                $sheet->mergeCells('A3:P3');
                $sheet->mergeCells('A4:I4');
                $sheet->mergeCells('J4:P4');

                // Agrupadores de Fila 5: UNIDADES (E5:J5) y BOLIVARES (K5:P5)
                $sheet->mergeCells('E5:J5');
                $sheet->mergeCells('K5:P5');

                $sheet->getStyle('E5:J5')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A8A']],
                ]);

                $sheet->getStyle('K5:P5')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '92400E'], 'size' => 10],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEF3C7']],
                ]);

                // Estilos de encabezados en Fila 6
                $sheet->getStyle('A6:J6')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 8],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A8A']],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
                ]);

                $sheet->getStyle('K6:P6')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '78350F'], 'size' => 8],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FDE68A']],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
                ]);

                $sheet->getRowDimension(5)->setRowHeight(20);
                $sheet->getRowDimension(6)->setRowHeight(28);

                // Filas de datos
                $startDataRow = 7;
                $endDataRow = $startDataRow + $this->rowCount - 1;

                if ($this->rowCount > 0) {
                    $sheet->getStyle("A{$startDataRow}:A{$endDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("B{$startDataRow}:B{$endDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $sheet->getStyle("C{$startDataRow}:C{$endDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $sheet->getStyle("D{$startDataRow}:P{$endDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    $sheet->getStyle("A{$startDataRow}:P{$endDataRow}")->applyFromArray([
                        'font' => ['size' => 8],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
                    ]);

                    // Fila de Totales
                    $totalRow = $endDataRow + 1;
                    $sheet->setCellValue("A{$totalRow}", 'TOTALES');
                    $sheet->mergeCells("A{$totalRow}:D{$totalRow}");

                    $columnsToSum = ['E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P'];
                    foreach ($columnsToSum as $col) {
                        $sheet->setCellValue("{$col}{$totalRow}", "=SUM({$col}{$startDataRow}:{$col}{$endDataRow})");
                    }

                    $sheet->getStyle("A{$totalRow}:P{$totalRow}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '0F172A']],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2E8F0']],
                        'borders' => [
                            'top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '475569']],
                            'bottom' => ['borderStyle' => Border::BORDER_DOUBLE, 'color' => ['rgb' => '0F172A']],
                            'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']],
                        ],
                    ]);
                    $sheet->getStyle("A{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("E{$totalRow}:P{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }

                // Configuración de impresión: Horizontal, 1 página de ancho
                $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
                $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);
                $sheet->getPageSetup()->setFitToWidth(1);
                $sheet->getPageSetup()->setFitToHeight(0);
                $sheet->getSheetView()->setView(SheetView::SHEETVIEW_NORMAL);
            },
        ];
    }
}
