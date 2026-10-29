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

        $periodTypeValue = is_object($this->book->period_type) ? $this->book->period_type->value : (string) $this->book->period_type;
        $periodLabel = ($periodTypeValue === 'fortnightly')
            ? ($this->book->fortnight == 1 ? "1RA QUINCENA (01 AL 15)" : "2DA QUINCENA (16 AL FIN DE MES)")
            : "MES COMPLETO";

        return [
            // Fila 1: Título Principal
            ['REGISTRO DETALLADO DE ENTRADAS Y SALIDAS DE INVENTARIO DE MERCANCÍAS'],
            // Fila 2: Base legal y condición fiscal
            [
                'Cumplimiento según el artículo 177 de la Ley de Impuesto Sobre la Renta (LISR) - SENIAT',
                '', '', '', '', '', '', '', '', '', '', '', '', '', '',
                'CONDICIÓN: ' . $taxpayerType,
            ],
            // Fila 3: Datos de la Empresa y Período
            [
                'RAZÓN SOCIAL: ' . $companyName,
                '', '', '', '', '',
                'RIF: ' . $companyRif,
                '', '',
                'MES: ' . $monthName . ' ' . $this->book->year,
                '', '',
                'PERÍODO: ' . $periodLabel,
            ],
            // Fila 4: Dirección fiscal
            ['DIRECCIÓN FISCAL: ' . $companyAddress],
            // Fila 5: Columnas del Libro de Inventario (Fondo blanco, sin fila agrupada intermedia)
            [
                'Nº',
                'CÓDIGO DEL PRODUCTO',
                'DESCRIPCIÓN DEL PRODUCTO',
                'COSTO PROMEDIO UNITARIO (BS)',
                'EXISTENCIA INICIAL',
                'ENTRADAS COMPRAS',
                'SALIDAS VENTAS',
                'RETIROS',
                'AUTOCONSUMOS',
                'EXISTENCIA FINAL',
                'VALOR ANTERIOR (BS)',
                'COSTO ENTRADAS (BS)',
                'COSTO SALIDAS (BS)',
                'COSTO RETIROS (BS)',
                'COSTO AUTOCONSUMOS (BS)',
                'VALOR DEL INVENTARIO (BS)',
            ],
        ];
    }

    public function map($entry): array
    {
        return [
            $entry->operation_number,
            $entry->product_code,
            $entry->product_description,
            (float) ($entry->unit_cost_bs ?? 0),
            (int) round((float) ($entry->initial_stock ?? 0)),
            (int) round((float) ($entry->purchase_entries ?? 0)),
            (int) round((float) ($entry->sales_exits ?? 0)),
            (int) round((float) ($entry->withdrawals ?? 0)),
            (int) round((float) ($entry->self_consumptions ?? 0)),
            (int) round((float) ($entry->final_stock ?? 0)),
            (float) ($entry->previous_value_bs ?? 0),
            (float) ($entry->purchase_entries_cost_bs ?? 0),
            (float) ($entry->sales_exits_cost_bs ?? 0),
            (float) ($entry->withdrawals_cost_bs ?? 0),
            (float) ($entry->self_consumptions_cost_bs ?? 0),
            (float) ($entry->final_inventory_value_bs ?? 0),
        ];
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_NUMBER,
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
            'D' => '#,##0.00',
            'E' => '#,##0',
            'F' => '#,##0',
            'G' => '#,##0',
            'H' => '#,##0',
            'I' => '#,##0',
            'J' => '#,##0',
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
        // Anchos de columna optimizados para visualización e impresión horizontal
        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(38);
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

        // Alineación y ajuste de texto
        $sheet->getStyle('A1:P5')->getAlignment()->setWrapText(true);
        $sheet->getStyle('C')->getAlignment()->setWrapText(true);

        return [
            1 => ['font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '000000']]],
            2 => ['font' => ['size' => 8.5, 'color' => ['rgb' => '333333']]],
            3 => ['font' => ['size' => 8.5, 'color' => ['rgb' => '000000']]],
            4 => ['font' => ['size' => 8.5, 'color' => ['rgb' => '333333']]],
            5 => ['font' => ['bold' => true, 'size' => 8, 'color' => ['rgb' => '000000']]],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // 1. Combinaciones limpias de la Cabecera (Filas 1 a 4)
                $sheet->mergeCells('A1:P1');
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getRowDimension(1)->setRowHeight(24);

                $sheet->mergeCells('A2:L2');
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->mergeCells('M2:P2');
                $sheet->getStyle('M2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle('M2')->getFont()->setBold(true);
                $sheet->getRowDimension(2)->setRowHeight(18);

                $sheet->mergeCells('A3:F3');
                $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->mergeCells('G3:I3');
                $sheet->getStyle('G3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->mergeCells('J3:L3');
                $sheet->getStyle('J3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->mergeCells('M3:P3');
                $sheet->getStyle('M3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getRowDimension(3)->setRowHeight(20);

                $sheet->mergeCells('A4:P4');
                $sheet->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getRowDimension(4)->setRowHeight(18);

                // Bordes delimitadores de la sección de cabecera
                $sheet->getStyle('A1:P4')->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']],
                ]);

                // 2. Fila 5: Encabezados de Columnas (Fondo blanco para optimizar tinta, texto negro, bordes definidos)
                $sheet->getRowDimension(5)->setRowHeight(30);
                $sheet->getStyle('A5:P5')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '000000'], 'size' => 8],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']],
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
                        'top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '000000']],
                        'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '000000']],
                    ],
                ]);

                // 3. Filas de Datos (Inicio en Fila 6)
                $startDataRow = 6;
                $endDataRow = $startDataRow + $this->rowCount - 1;

                if ($this->rowCount > 0) {
                    $sheet->getStyle("A{$startDataRow}:A{$endDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("B{$startDataRow}:B{$endDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $sheet->getStyle("C{$startDataRow}:C{$endDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $sheet->getStyle("D{$startDataRow}:P{$endDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    $sheet->getStyle("A{$startDataRow}:P{$endDataRow}")->applyFromArray([
                        'font' => ['size' => 8, 'color' => ['rgb' => '000000']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
                    ]);

                    // 4. Fila de Totales Consolidados (Fondo blanco, texto negro, bordes dobles, totalización asegurada)
                    $totalRow = $endDataRow + 1;
                    $sheet->setCellValue("A{$totalRow}", 'TOTALES CONSOLIDADOS');
                    $sheet->mergeCells("A{$totalRow}:D{$totalRow}");

                    // Columnas de unidades (Enteros)
                    $unitCols = ['E', 'F', 'G', 'H', 'I', 'J'];
                    foreach ($unitCols as $col) {
                        $sheet->setCellValue("{$col}{$totalRow}", "=SUM({$col}{$startDataRow}:{$col}{$endDataRow})");
                        $sheet->getStyle("{$col}{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');
                    }

                    // Columnas de importes en Bs (Decimales con 2 dígitos)
                    $bsCols = ['K', 'L', 'M', 'N', 'O', 'P'];
                    foreach ($bsCols as $col) {
                        $sheet->setCellValue("{$col}{$totalRow}", "=SUM({$col}{$startDataRow}:{$col}{$endDataRow})");
                        $sheet->getStyle("{$col}{$totalRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                    }

                    $sheet->getRowDimension($totalRow)->setRowHeight(22);
                    $sheet->getStyle("A{$totalRow}:P{$totalRow}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 8.5, 'color' => ['rgb' => '000000']],
                        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']],
                        'borders' => [
                            'top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
                            'bottom' => ['borderStyle' => Border::BORDER_DOUBLE, 'color' => ['rgb' => '000000']],
                            'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '94A3B8']],
                        ],
                    ]);
                    $sheet->getStyle("A{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("E{$totalRow}:P{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }

                // 5. Configuración de impresión: Horizontal, 1 página de ancho
                $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
                $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);
                $sheet->getPageSetup()->setFitToWidth(1);
                $sheet->getPageSetup()->setFitToHeight(0);
                $sheet->getSheetView()->setView(SheetView::SHEETVIEW_NORMAL);
            },
        ];
    }
}
