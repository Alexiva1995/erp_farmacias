<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\GeneralSetting;
use App\Models\VatRetentionBook;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\SheetView;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class VatRetentionBookExport implements FromCollection, WithHeadings, WithMapping, WithColumnFormatting, WithStyles, WithEvents, WithStrictNullComparison
{
    protected GeneralSetting $settings;
    protected int $rowCount = 0;

    public function __construct(
        protected VatRetentionBook $book,
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
            ['RELACIÓN Y REPORTE DE RETENCIONES DE IVA A PROVEEDORES (PORTAL FISCAL SENIAT)'],
            // Fila 2: Base legal y condición
            [
                'Providencia Administrativa SNAT/2015/0049 - Declaración Informativa de Retenciones de IVA',
                '', '', '', '', '', '', '', '', '', '', '', '',
                'CONDICIÓN: ' . $taxpayerType,
            ],
            // Fila 3: Ficha de datos
            [
                'RAZÓN SOCIAL: ' . $companyName,
                '', '', '', '',
                'RIF AGENTE: ' . $companyRif,
                '', '',
                'MES: ' . $monthName . ' ' . $this->book->year,
                '', '',
                'PERÍODO: ' . $periodLabel,
            ],
            // Fila 4: Dirección
            ['DIRECCIÓN FISCAL: ' . $companyAddress],
            // Fila 5: Encabezados de columnas (Fondo blanco, bordes limpios)
            [
                'Nº',
                'RIF AGENTE',
                'PER. FISCAL',
                'FECHA DOC',
                'OPER.',
                'TIPO',
                'RIF PROVEEDOR',
                'RAZÓN SOCIAL PROVEEDOR',
                'Nº FACTURA',
                'Nº CONTROL',
                'TOTAL DOC (BS)',
                'BASE IMPONIBLE (BS)',
                'IVA RETENIDO (BS)',
                'DOC AFECTADO',
                'Nº COMPROBANTE',
                'EXENTO (BS)',
                'ALÍCUOTA %',
            ],
        ];
    }

    public function map($entry): array
    {
        return [
            $entry->operation_number,
            $entry->agent_rif,
            $entry->fiscal_period,
            $entry->document_date ? Carbon::parse($entry->document_date)->format('Y-m-d') : '',
            $entry->operation_type,
            $entry->document_type,
            $entry->supplier_rif,
            $entry->supplier_name,
            $entry->document_number,
            $entry->control_number,
            (float) ($entry->total_document_amount ?? 0),
            (float) ($entry->taxable_base ?? 0),
            (float) ($entry->withheld_amount ?? 0),
            $entry->affected_document_number ?: '0',
            $entry->retention_number,
            (float) ($entry->exempt_amount ?? 0),
            (float) ($entry->tax_rate ?? 16.0),
        ];
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_NUMBER,
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
            'D' => 'yyyy-mm-dd',
            'E' => NumberFormat::FORMAT_TEXT,
            'F' => NumberFormat::FORMAT_TEXT,
            'G' => NumberFormat::FORMAT_TEXT,
            'H' => NumberFormat::FORMAT_TEXT,
            'I' => NumberFormat::FORMAT_TEXT,
            'J' => NumberFormat::FORMAT_TEXT,
            'K' => '#,##0.00;-#,##0.00;"0.00"',
            'L' => '#,##0.00;-#,##0.00;"0.00"',
            'M' => '#,##0.00;-#,##0.00;"0.00"',
            'N' => NumberFormat::FORMAT_TEXT,
            'O' => NumberFormat::FORMAT_TEXT,
            'P' => '#,##0.00;-#,##0.00;"0.00"',
            'Q' => '#,##0.0;-#,##0.0;"0.0"',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(13);
        $sheet->getColumnDimension('C')->setWidth(11);
        $sheet->getColumnDimension('D')->setWidth(12);
        $sheet->getColumnDimension('E')->setWidth(7);
        $sheet->getColumnDimension('F')->setWidth(7);
        $sheet->getColumnDimension('G')->setWidth(14);
        $sheet->getColumnDimension('H')->setWidth(30);
        $sheet->getColumnDimension('I')->setWidth(16);
        $sheet->getColumnDimension('J')->setWidth(15);
        $sheet->getColumnDimension('K')->setWidth(17);
        $sheet->getColumnDimension('L')->setWidth(17);
        $sheet->getColumnDimension('M')->setWidth(17);
        $sheet->getColumnDimension('N')->setWidth(14);
        $sheet->getColumnDimension('O')->setWidth(18);
        $sheet->getColumnDimension('P')->setWidth(15);
        $sheet->getColumnDimension('Q')->setWidth(12);

        $sheet->getStyle('A1:Q5')->getAlignment()->setWrapText(true);
        $sheet->getStyle('H')->getAlignment()->setWrapText(true);

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

                // Combinaciones de Cabecera
                $sheet->mergeCells('A1:Q1');
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getRowDimension(1)->setRowHeight(24);

                $sheet->mergeCells('A2:M2');
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->mergeCells('N2:Q2');
                $sheet->getStyle('N2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle('N2')->getFont()->setBold(true);
                $sheet->getRowDimension(2)->setRowHeight(18);

                $sheet->mergeCells('A3:E3');
                $sheet->mergeCells('F3:H3');
                $sheet->mergeCells('I3:L3');
                $sheet->mergeCells('M3:Q3');
                $sheet->getStyle('M3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getRowDimension(3)->setRowHeight(20);

                $sheet->mergeCells('A4:Q4');
                $sheet->getRowDimension(4)->setRowHeight(18);

                $sheet->getStyle('A1:Q4')->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']],
                ]);

                // Fila 5: Encabezados de columnas en blanco con bordes negros
                $sheet->getRowDimension(5)->setRowHeight(28);
                $sheet->getStyle('A5:Q5')->applyFromArray([
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

                // Filas de Datos
                $startDataRow = 6;
                $endDataRow = $startDataRow + $this->rowCount - 1;

                if ($this->rowCount > 0) {
                    $sheet->getStyle("A{$startDataRow}:A{$endDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("B{$startDataRow}:G{$endDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("H{$startDataRow}:H{$endDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $sheet->getStyle("I{$startDataRow}:J{$endDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("K{$startDataRow}:M{$endDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $sheet->getStyle("N{$startDataRow}:O{$endDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("P{$startDataRow}:Q{$endDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    $sheet->getStyle("A{$startDataRow}:Q{$endDataRow}")->applyFromArray([
                        'font' => ['size' => 8, 'color' => ['rgb' => '000000']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
                    ]);

                    // Fila de Totales Consolidados
                    $totalRow = $endDataRow + 1;
                    $sheet->setCellValue("A{$totalRow}", 'TOTALES CONSOLIDADOS');
                    $sheet->mergeCells("A{$totalRow}:J{$totalRow}");

                    $columnsToSum = ['K', 'L', 'M', 'P'];
                    foreach ($columnsToSum as $col) {
                        $sheet->setCellValue("{$col}{$totalRow}", "=SUM({$col}{$startDataRow}:{$col}{$endDataRow})");
                        $sheet->getStyle("{$col}{$totalRow}")->getNumberFormat()->setFormatCode('#,##0.00;-#,##0.00;"0.00"');
                    }

                    $sheet->getRowDimension($totalRow)->setRowHeight(22);
                    $sheet->getStyle("A{$totalRow}:Q{$totalRow}")->applyFromArray([
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
                    $sheet->getStyle("K{$totalRow}:Q{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }

                $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
                $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);
                $sheet->getPageSetup()->setFitToWidth(1);
                $sheet->getPageSetup()->setFitToHeight(0);
                $sheet->getSheetView()->setView(SheetView::SHEETVIEW_NORMAL);
            },
        ];
    }
}
